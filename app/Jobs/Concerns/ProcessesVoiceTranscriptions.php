<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\DTOs\Voice\VoiceConfig;
use Illuminate\Support\Facades\Storage;

/**
 * Общая логика для Jobs обработки голосовых транскрипций.
 */
trait ProcessesVoiceTranscriptions
{
    protected ?VoiceConfig $voiceConfig = null;

    protected function getVoiceConfig(): VoiceConfig
    {
        return $this->voiceConfig ??= VoiceConfig::fromConfig();
    }

    protected function initializeVoiceJobConfig(): void
    {
        $config = $this->getVoiceConfig();

        $this->tries = $config->jobTries;
        $this->backoff = $config->jobBackoff;
        $this->onQueue($config->queue);
    }

    /**
     * Удалить временный файл после обработки.
     */
    protected function cleanupTempFile(string $path): void
    {
        $disk = Storage::disk($this->getVoiceConfig()->storageDisk);

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
