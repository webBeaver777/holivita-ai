<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatRequestDTO;
use App\DTOs\AI\ChatResponseDTO;
use App\DTOs\AI\SummarizeRequestDTO;
use App\DTOs\AI\SummarizeResponseDTO;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\Onboarding\ProcessOnboardingMessageJob;
use App\Jobs\Onboarding\ProcessOnboardingStartJob;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для управления онбордингом.
 */
final class OnboardingService
{
    public function __construct(
        private readonly AIClientInterface $aiClient,
    ) {}

    /**
     * Проверить возможность начала онбординга.
     *
     * @return array{can_start: bool, reason: string|null, active_session_id: string|null}
     */
    public function canStartOnboarding(int $userId): array
    {
        // Автоистечение старых сессий
        $this->expireStaleSessionsForUser($userId);

        $activeSession = $this->getActiveSession($userId);

        if ($activeSession) {
            return [
                'can_start' => false,
                'reason' => 'У вас уже есть активная сессия онбординга.',
                'active_session_id' => $activeSession->id,
            ];
        }

        return ['can_start' => true, 'reason' => null, 'active_session_id' => null];
    }

    /**
     * Получить активную сессию пользователя.
     */
    public function getActiveSession(int $userId): ?OnboardingSession
    {
        return OnboardingSession::forUser($userId)
            ->inProgress()
            ->latest()
            ->first();
    }

    /**
     * Отменить сессию.
     */
    public function cancelSession(OnboardingSession $session): void
    {
        if (! $session->isActive()) {
            return;
        }

        $session->markAsCancelled();

        Log::info('Cancelled onboarding session', ['session_id' => $session->id]);
    }

    /**
     * Отметить устаревшие сессии пользователя как истёкшие.
     */
    public function expireStaleSessionsForUser(int $userId): int
    {
        $expiryHours = config('ai.onboarding.session_expiry_hours', 24);

        $staleSessions = OnboardingSession::forUser($userId)
            ->stale($expiryHours)
            ->get();

        foreach ($staleSessions as $session) {
            $session->markAsExpired();
            Log::info('Expired stale onboarding session', ['session_id' => $session->id]);
        }

        return $staleSessions->count();
    }

    /**
     * Получить или создать активную сессию.
     */
    public function getOrCreateSession(int $userId): OnboardingSession
    {
        $session = OnboardingSession::forUser($userId)
            ->inProgress()
            ->latest()
            ->first();

        if ($session) {
            return $session;
        }

        $session = OnboardingSession::create(['user_id' => $userId]);

        Log::info('Created new onboarding session', [
            'session_id' => $session->id,
            'user_id' => $userId,
        ]);

        return $session;
    }

    /**
     * Начать онбординг (получить приветствие).
     *
     * @throws \App\Exceptions\AIClientException
     */
    public function startOnboarding(OnboardingSession $session): ChatResponseDTO
    {
        return DB::transaction(function () use ($session) {
            $response = $this->aiClient->chat(new ChatRequestDTO(
                message: config('ai.onboarding.welcome_prompt'),
                sessionId: $session->id,
            ));

            $this->saveMessage($session, MessageRole::ASSISTANT, $response->message);

            Log::info('Started onboarding session', ['session_id' => $session->id]);

            return $response;
        });
    }

    /**
     * Обработать сообщение пользователя.
     *
     * @throws \App\Exceptions\AIClientException
     */
    public function processMessage(OnboardingSession $session, string $message): ChatResponseDTO
    {
        return DB::transaction(function () use ($session, $message) {
            $this->saveMessage($session, MessageRole::USER, $message);

            $response = $this->aiClient->chat(new ChatRequestDTO(
                message: $message,
                sessionId: $session->id,
            ));

            $this->saveMessage($session, MessageRole::ASSISTANT, $response->message);

            Log::info('Processed onboarding message', [
                'session_id' => $session->id,
                'is_complete' => $response->isComplete,
            ]);

            return $response;
        });
    }

    /**
     * Завершить онбординг и получить суммаризацию.
     *
     * @throws \App\Exceptions\AIClientException
     */
    public function completeOnboarding(OnboardingSession $session): SummarizeResponseDTO
    {
        if ($session->isCompleted()) {
            return new SummarizeResponseDTO(summary: $session->summary_json ?? []);
        }

        return DB::transaction(function () use ($session) {
            $response = $this->aiClient->summarize(new SummarizeRequestDTO(
                messages: $this->getConversationHistory($session),
                sessionId: $session->id,
            ));

            $session->markAsCompleted($response->summary);

            Log::info('Completed onboarding session', ['session_id' => $session->id]);

            return $response;
        });
    }

    /**
     * Получить историю диалога.
     *
     * @return array<array{role: string, content: string}>
     */
    public function getConversationHistory(OnboardingSession $session): array
    {
        return $session->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn (OnboardingMessage $msg) => $msg->toAIFormat())
            ->toArray();
    }

    /**
     * Найти сессию по ID и user_id.
     */
    public function findSession(string $sessionId, int $userId): ?OnboardingSession
    {
        return OnboardingSession::forUser($userId)
            ->where('id', $sessionId)
            ->first();
    }

    /**
     * Получить последнюю сессию пользователя.
     */
    public function getLatestSession(int $userId): ?OnboardingSession
    {
        return OnboardingSession::forUser($userId)->latest()->first();
    }

    /**
     * Асинхронно начать онбординг (отправить в очередь).
     */
    public function startOnboardingAsync(OnboardingSession $session): void
    {
        ProcessOnboardingStartJob::dispatch($session);

        Log::info('Dispatched onboarding start job', ['session_id' => $session->id]);
    }

    /**
     * Асинхронно обработать сообщение пользователя (отправить в очередь).
     */
    public function processMessageAsync(OnboardingSession $session, string $message): OnboardingMessage
    {
        $userMessage = OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::USER,
            'content' => $message,
            'status' => MessageStatus::PENDING,
        ]);

        ProcessOnboardingMessageJob::dispatch($userMessage, $session);

        Log::info('Dispatched onboarding message job', [
            'session_id' => $session->id,
            'message_id' => $userMessage->id,
        ]);

        return $userMessage;
    }

    /**
     * Получить статус обработки последнего сообщения.
     *
     * @return array{status: string, message: string|null, error: string|null}
     */
    public function getMessageStatus(OnboardingSession $session): array
    {
        $lastAssistantMessage = $session->messages()
            ->assistant()
            ->latest('id')
            ->first();

        if (! $lastAssistantMessage) {
            return [
                'status' => MessageStatus::PENDING->value,
                'message' => null,
                'error' => null,
            ];
        }

        return [
            'status' => $lastAssistantMessage->status->value,
            'message' => $lastAssistantMessage->isCompleted() ? $lastAssistantMessage->content : null,
            'error' => $lastAssistantMessage->isFailed() ? $lastAssistantMessage->error_message : null,
        ];
    }

    /**
     * Проверить, есть ли сообщения в обработке.
     */
    public function hasProcessingMessages(OnboardingSession $session): bool
    {
        return $session->messages()->inProgress()->exists();
    }

    private function saveMessage(OnboardingSession $session, MessageRole $role, string $content): void
    {
        OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => $role,
            'content' => $content,
        ]);
    }
}
