<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatRequestDTO;
use App\DTOs\AI\ChatResponseDTO;
use App\DTOs\AI\SummarizeRequestDTO;
use App\DTOs\AI\SummarizeResponseDTO;
use App\Enums\MessageRole;
use App\Enums\OnboardingStatus;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для управления онбордингом.
 */
final class OnboardingService
{
    private const WELCOME_PROMPT = 'Начни онбординг. Поприветствуй пользователя тепло и задай первый вопрос.';

    public function __construct(
        private readonly AIClientInterface $aiClient,
    ) {}

    /**
     * Проверить возможность начала онбординга для пользователя.
     *
     * @return array{can_start: bool, reason: string|null}
     */
    public function canStartOnboarding(int $userId): array
    {
        $activeSession = OnboardingSession::query()
            ->where('user_id', $userId)
            ->where('status', OnboardingStatus::IN_PROGRESS)
            ->exists();

        if ($activeSession) {
            return [
                'can_start' => false,
                'reason' => 'У вас уже есть активная сессия онбординга.',
            ];
        }

        $completedSession = OnboardingSession::query()
            ->where('user_id', $userId)
            ->where('status', OnboardingStatus::COMPLETED)
            ->exists();

        if ($completedSession) {
            return [
                'can_start' => false,
                'reason' => 'Вы уже прошли онбординг ранее.',
            ];
        }

        return ['can_start' => true, 'reason' => null];
    }

    /**
     * Получить или создать активную сессию для пользователя.
     */
    public function getOrCreateSession(int $userId): OnboardingSession
    {
        $session = OnboardingSession::query()
            ->where('user_id', $userId)
            ->where('status', OnboardingStatus::IN_PROGRESS)
            ->latest()
            ->first();

        if (! $session) {
            $session = OnboardingSession::create([
                'user_id' => $userId,
                'status' => OnboardingStatus::IN_PROGRESS,
            ]);

            Log::info('Created new onboarding session', [
                'session_id' => $session->id,
                'user_id' => $userId,
            ]);
        }

        return $session;
    }

    /**
     * Начать онбординг (получить приветственное сообщение).
     *
     * @throws \App\Exceptions\AIClientException
     */
    public function startOnboarding(OnboardingSession $session): ChatResponseDTO
    {
        return DB::transaction(function () use ($session) {
            $request = new ChatRequestDTO(
                message: self::WELCOME_PROMPT,
                sessionId: $session->id,
            );

            $response = $this->aiClient->chat($request);

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

            $request = new ChatRequestDTO(
                message: $message,
                conversationHistory: $this->getConversationHistory($session),
                sessionId: $session->id,
            );

            $response = $this->aiClient->chat($request);

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
            return new SummarizeResponseDTO(
                summary: $session->summary_json ?? [],
            );
        }

        return DB::transaction(function () use ($session) {
            $messages = $this->getConversationHistory($session);

            $request = new SummarizeRequestDTO(
                messages: $messages,
                sessionId: $session->id,
            );

            $response = $this->aiClient->summarize($request);

            $session->markAsCompleted($response->summary);

            Log::info('Completed onboarding session', [
                'session_id' => $session->id,
            ]);

            return $response;
        });
    }

    /**
     * Получить историю диалога сессии.
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
        return OnboardingSession::query()
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Получить последнюю сессию пользователя.
     */
    public function getLatestSession(int $userId): ?OnboardingSession
    {
        return OnboardingSession::query()
            ->where('user_id', $userId)
            ->latest()
            ->first();
    }

    private function saveMessage(OnboardingSession $session, MessageRole $role, string $content): OnboardingMessage
    {
        return OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => $role,
            'content' => $content,
        ]);
    }
}
