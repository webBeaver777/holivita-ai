<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\DTOs\Onboarding\OnboardingConfig;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;

/**
 * Общая логика для Jobs обработки онбординга.
 */
trait ProcessesOnboardingMessages
{
    protected ?OnboardingConfig $onboardingConfig = null;

    protected function getOnboardingConfig(): OnboardingConfig
    {
        return $this->onboardingConfig ??= OnboardingConfig::fromConfig();
    }

    protected function initializeJobConfig(): void
    {
        $config = $this->getOnboardingConfig();

        $this->tries = $config->jobTries;
        $this->backoff = $config->jobBackoff;
        $this->onQueue($config->queue);
    }

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

    protected function getWelcomePrompt(): string
    {
        return $this->getOnboardingConfig()->welcomePrompt;
    }
}
