<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Voice;

use App\DTOs\Voice\VoiceConfig;
use App\Exceptions\VoiceTranscriptionException;
use App\Services\Voice\AnythingLLMVoiceClient;
use App\Services\Voice\OpenAIVoiceClient;
use App\Services\Voice\VoiceClientFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoiceClientFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_anythingllm_client(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'anythingllm',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [
                'anythingllm' => [
                    'enabled' => true,
                    'api_url' => 'http://localhost:3001',
                    'api_key' => 'test-key',
                    'timeout' => 60,
                ],
            ],
        );

        $factory = new VoiceClientFactory($config);
        $client = $factory->create('anythingllm');

        $this->assertInstanceOf(AnythingLLMVoiceClient::class, $client);
    }

    #[Test]
    public function it_creates_openai_client(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'openai',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [
                'openai' => [
                    'enabled' => true,
                    'api_key' => 'sk-test',
                    'model' => 'whisper-1',
                    'timeout' => 60,
                ],
            ],
        );

        $factory = new VoiceClientFactory($config);
        $client = $factory->create('openai');

        $this->assertInstanceOf(OpenAIVoiceClient::class, $client);
    }

    #[Test]
    public function it_throws_exception_for_unknown_provider(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'anythingllm',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [],
        );

        $factory = new VoiceClientFactory($config);

        $this->expectException(VoiceTranscriptionException::class);
        $this->expectExceptionMessage('Провайдер unknown недоступен');

        $factory->create('unknown');
    }

    #[Test]
    public function it_uses_default_config_when_not_provided(): void
    {
        config([
            'voice.default_provider' => 'anythingllm',
            'voice.max_file_size' => 25 * 1024 * 1024,
            'voice.timeout' => 60,
            'voice.default_language' => 'ru',
            'voice.providers' => [
                'anythingllm' => [
                    'enabled' => true,
                    'api_url' => 'http://localhost:3001',
                    'api_key' => 'test',
                    'timeout' => 60,
                ],
            ],
        ]);

        $factory = new VoiceClientFactory;
        $client = $factory->create('anythingllm');

        $this->assertInstanceOf(AnythingLLMVoiceClient::class, $client);
    }
}
