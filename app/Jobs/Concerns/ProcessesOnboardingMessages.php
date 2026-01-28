<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;

/**
 * Общая логика для Jobs обработки онбординга.
 */
trait ProcessesOnboardingMessages
{
    protected function createPendingAssistantMessage(OnboardingSession $session): OnboardingMessage
    {
        return OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT,
            'content' => '',
            'status' => MessageStatus::PENDING,
        ]);
    }

    protected function markSessionMessagesAsFailed(OnboardingSession $session, string $error): void
    {
        $session->messages()
            ->processing()
            ->update([
                'status' => MessageStatus::FAILED,
                'error_message' => $error,
            ]);
    }

    protected function getQueueName(): string
    {
        return config('ai.onboarding.queue', 'onboarding');
    }
}
