<?php

declare(strict_types=1);

namespace App\DTOs\Onboarding;

/**
 * DTO для конфигурации онбординга.
 * Централизует доступ к настройкам из config/ai.php.
 */
final readonly class OnboardingConfig
{
    public function __construct(
        public string $welcomePrompt,
        public string $queue,
        public int $jobTries,
        public int $jobBackoff,
        public int $sessionExpiryHours,
    ) {}

    /**
     * Создать из конфигурации приложения.
     */
    public static function fromConfig(): self
    {
        return new self(
            welcomePrompt: (string) config('ai.onboarding.welcome_prompt', ''),
            queue: (string) config('ai.onboarding.queue', 'onboarding'),
            jobTries: (int) config('ai.onboarding.job_tries', 3),
            jobBackoff: (int) config('ai.onboarding.job_backoff', 10),
            sessionExpiryHours: (int) config('ai.onboarding.session_expiry_hours', 24),
        );
    }
}
