<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\DTOs\Voice\VoiceConfig;

/**
 * Фабрика для создания клиента транскрипции OpenAI.
 */
class VoiceClientFactory
{
    private readonly VoiceConfig $config;

    public function __construct(?VoiceConfig $config = null)
    {
        $this->config = $config ?? VoiceConfig::fromConfig();
    }

    /**
     * Создать OpenAI клиента.
     */
    public function create(): OpenAIVoiceClient
    {
        return new OpenAIVoiceClient(
            apiKey: $this->config->apiKey,
            model: $this->config->model,
            timeout: $this->config->timeout,
        );
    }
}
