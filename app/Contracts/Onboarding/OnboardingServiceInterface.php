<?php

declare(strict_types=1);

namespace App\Contracts\Onboarding;

use App\DTOs\AI\ChatResponseDTO;
use App\DTOs\AI\SummarizeResponseDTO;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;

/**
 * Контракт для сервиса онбординга.
 */
interface OnboardingServiceInterface
{
    /**
     * Проверить, может ли пользователь начать онбординг.
     *
     * @return array{can_start: bool, reason: ?string, active_session_id: ?string}
     */
    public function canStartOnboarding(int $userId): array;

    /**
     * Получить или создать сессию для пользователя.
     */
    public function getOrCreateSession(int $userId): OnboardingSession;

    /**
     * Получить активную сессию пользователя.
     */
    public function getActiveSession(int $userId): ?OnboardingSession;

    /**
     * Начать онбординг (получить приветственное сообщение).
     */
    public function startOnboarding(OnboardingSession $session): ChatResponseDTO;

    /**
     * Начать онбординг асинхронно.
     */
    public function startOnboardingAsync(OnboardingSession $session): void;

    /**
     * Обработать сообщение пользователя.
     */
    public function processMessage(OnboardingSession $session, string $message): ChatResponseDTO;

    /**
     * Обработать сообщение асинхронно.
     */
    public function processMessageAsync(OnboardingSession $session, string $message): OnboardingMessage;

    /**
     * Получить статус последнего сообщения.
     *
     * @return array{status: string, message: ?string, error: ?string}
     */
    public function getMessageStatus(OnboardingSession $session): array;

    /**
     * Проверить, есть ли обрабатываемые сообщения.
     */
    public function hasProcessingMessages(OnboardingSession $session): bool;

    /**
     * Завершить онбординг и получить суммаризацию.
     */
    public function completeOnboarding(OnboardingSession $session): SummarizeResponseDTO;

    /**
     * Получить историю диалога.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getConversationHistory(OnboardingSession $session): array;

    /**
     * Найти сессию по ID и user_id.
     */
    public function findSession(string $sessionId, int $userId): ?OnboardingSession;

    /**
     * Отменить сессию.
     */
    public function cancelSession(OnboardingSession $session): void;

    /**
     * Истечь устаревшие сессии пользователя.
     */
    public function expireStaleSessionsForUser(int $userId): int;
}
