<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\Voice;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;
use App\DTOs\Voice\VoiceConfig;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TranscriptionDTOsTest extends TestCase
{
    #[Test]
    public function transcription_request_dto_holds_correct_data(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $sessionId = '550e8400-e29b-41d4-a716-446655440000';

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'ru',
            sessionId: $sessionId,
        );

        $this->assertSame($audioFile, $dto->audio);
        $this->assertEquals('ru', $dto->language);
        $this->assertEquals($sessionId, $dto->sessionId);
    }

    #[Test]
    public function transcription_request_dto_returns_file_path(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'en',
        );

        $this->assertNotEmpty($dto->getFilePath());
    }

    #[Test]
    public function transcription_request_dto_returns_mime_type(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'en',
        );

        $this->assertNotNull($dto->getMimeType());
    }

    #[Test]
    public function transcription_request_dto_returns_file_name(): void
    {
        $audioFile = UploadedFile::fake()->create('test_audio.webm', 1024, 'audio/webm');

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'en',
        );

        $this->assertStringContainsString('test_audio', $dto->getFileName());
    }

    #[Test]
    public function transcription_response_dto_holds_correct_data(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: 'Привет, мир!',
            language: 'ru',
            confidence: 0.95,
            duration: 2.5,
            provider: 'anythingllm',
        );

        $this->assertEquals('Привет, мир!', $dto->text);
        $this->assertEquals('ru', $dto->language);
        $this->assertEquals(0.95, $dto->confidence);
        $this->assertEquals(2.5, $dto->duration);
        $this->assertEquals('anythingllm', $dto->provider);
    }

    #[Test]
    public function transcription_response_dto_is_empty_returns_true_for_empty_text(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: '',
            language: 'ru',
            provider: 'anythingllm',
        );

        $this->assertTrue($dto->isEmpty());
    }

    #[Test]
    public function transcription_response_dto_is_empty_returns_true_for_whitespace_text(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: '   ',
            language: 'ru',
            provider: 'anythingllm',
        );

        $this->assertTrue($dto->isEmpty());
    }

    #[Test]
    public function transcription_response_dto_is_empty_returns_false_for_valid_text(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: 'Hello',
            language: 'en',
            provider: 'openai',
        );

        $this->assertFalse($dto->isEmpty());
    }

    #[Test]
    public function voice_config_can_be_created_from_config(): void
    {
        config([
            'voice.default_provider' => 'openai',
            'voice.max_file_size' => 10 * 1024 * 1024,
            'voice.timeout' => 30,
            'voice.default_language' => 'en',
            'voice.providers' => [
                'anythingllm' => [
                    'enabled' => true,
                    'api_url' => 'http://localhost:3001',
                    'api_key' => 'test-key',
                    'timeout' => 60,
                ],
                'openai' => [
                    'enabled' => true,
                    'api_key' => 'sk-test',
                    'model' => 'whisper-1',
                    'timeout' => 60,
                ],
            ],
        ]);

        $config = VoiceConfig::fromConfig();

        $this->assertEquals('openai', $config->defaultProvider);
        $this->assertEquals(10 * 1024 * 1024, $config->maxFileSize);
        $this->assertEquals(30, $config->timeout);
        $this->assertEquals('en', $config->defaultLanguage);
    }

    #[Test]
    public function voice_config_returns_provider_config(): void
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
                ],
            ],
        );

        $providerConfig = $config->getProviderConfig('anythingllm');

        $this->assertEquals('http://localhost:3001', $providerConfig['api_url']);
        $this->assertEquals('test-key', $providerConfig['api_key']);
    }

    #[Test]
    public function voice_config_returns_empty_array_for_unknown_provider(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'anythingllm',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [],
        );

        $providerConfig = $config->getProviderConfig('unknown');

        $this->assertEmpty($providerConfig);
    }

    #[Test]
    public function voice_config_checks_provider_enabled(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'anythingllm',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [
                'anythingllm' => ['enabled' => true],
                'openai' => ['enabled' => false],
            ],
        );

        $this->assertTrue($config->isProviderEnabled('anythingllm'));
        $this->assertFalse($config->isProviderEnabled('openai'));
        $this->assertFalse($config->isProviderEnabled('unknown'));
    }

    #[Test]
    public function voice_config_returns_enabled_providers(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'anythingllm',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [
                'anythingllm' => ['enabled' => true],
                'openai' => ['enabled' => false],
                'azure' => ['enabled' => true],
            ],
        );

        $enabledProviders = $config->getEnabledProviders();

        $this->assertContains('anythingllm', $enabledProviders);
        $this->assertContains('azure', $enabledProviders);
        $this->assertNotContains('openai', $enabledProviders);
    }
}
