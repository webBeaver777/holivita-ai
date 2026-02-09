<?php

declare(strict_types=1);

namespace App\DTOs\Voice;

/**
 * DTO для конфигурации голосового ввода.
 */
final readonly class VoiceConfig
{
    public function __construct(
        public int $maxFileSize,
        public int $timeout,
        public string $defaultLanguage,
        public string $apiKey,
        public string $model,
        public string $queue = 'onboarding',
        public int $jobTries = 3,
        public int $jobBackoff = 10,
        public string $storageDisk = 'local',
        public string $storagePath = 'voice-uploads',
    ) {}

    /**
     * Создать из конфигурации приложения.
     */
    public static function fromConfig(): self
    {
        return new self(
            maxFileSize: (int) config('voice.max_file_size', 25 * 1024 * 1024),
            timeout: (int) config('voice.timeout', 60),
            defaultLanguage: (string) config('voice.default_language', 'ru'),
            apiKey: (string) config('voice.openai.api_key', ''),
            model: (string) config('voice.openai.model', 'whisper-1'),
            queue: (string) config('ai.onboarding.queue', 'onboarding'),
            jobTries: (int) config('ai.onboarding.job_tries', 3),
            jobBackoff: (int) config('ai.onboarding.job_backoff', 10),
            storageDisk: (string) config('voice.storage_disk', 'local'),
            storagePath: (string) config('voice.storage_path', 'voice-uploads'),
        );
    }

    /**
     * Проверить, настроен ли API ключ.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey);
    }
}
