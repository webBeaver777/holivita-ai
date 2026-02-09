<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Voice;

use App\Contracts\Voice\VoiceTranscriptionInterface;
use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;
use App\DTOs\Voice\VoiceConfig;
use App\Exceptions\VoiceTranscriptionException;
use App\Services\Voice\VoiceClientFactory;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Http\UploadedFile;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoiceTranscriptionServiceTest extends TestCase
{
    private VoiceClientFactory $mockFactory;

    private VoiceTranscriptionInterface $mockClient;

    private VoiceConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $this->mockFactory = Mockery::mock(VoiceClientFactory::class);

        $this->config = new VoiceConfig(
            defaultProvider: 'openai',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [
                'openai' => ['enabled' => true, 'api_key' => 'sk-test'],
                'anythingllm' => ['enabled' => true, 'api_url' => 'http://localhost:3001', 'api_key' => 'test'],
            ],
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_transcribes_audio_successfully(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $request = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'ru',
        );

        $expectedResponse = new TranscriptionResponseDTO(
            text: 'Привет, мир!',
            language: 'ru',
            confidence: 0.95,
            duration: 2.5,
            provider: 'anythingllm',
        );

        $this->mockClient->shouldReceive('transcribe')
            ->once()
            ->andReturn($expectedResponse);

        $this->mockFactory->shouldReceive('create')
            ->with('anythingllm')
            ->once()
            ->andReturn($this->mockClient);

        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);
        $response = $service->transcribe($request, 'anythingllm');

        $this->assertEquals('Привет, мир!', $response->text);
        $this->assertEquals('ru', $response->language);
        $this->assertEquals(0.95, $response->confidence);
        $this->assertEquals('anythingllm', $response->provider);
    }

    #[Test]
    public function it_uses_default_provider_when_none_specified(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $request = new TranscriptionRequestDTO(audio: $audioFile, language: 'ru');

        $expectedResponse = new TranscriptionResponseDTO(
            text: 'Test',
            language: 'ru',
            provider: 'openai',
        );

        $this->mockClient->shouldReceive('transcribe')->andReturn($expectedResponse);
        $this->mockFactory->shouldReceive('create')
            ->with('openai')
            ->andReturn($this->mockClient);

        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);
        $response = $service->transcribe($request);

        $this->assertEquals('openai', $response->provider);
    }

    #[Test]
    public function it_throws_exception_when_provider_disabled(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'anythingllm',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [
                'anythingllm' => ['enabled' => false],
                'openai' => ['enabled' => false],
            ],
        );

        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $request = new TranscriptionRequestDTO(audio: $audioFile, language: 'ru');

        $this->expectException(VoiceTranscriptionException::class);

        $service = new VoiceTranscriptionService($this->mockFactory, $config);
        $service->transcribe($request, 'anythingllm');
    }

    #[Test]
    public function it_falls_back_to_secondary_provider_on_failure(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $request = new TranscriptionRequestDTO(audio: $audioFile, language: 'ru');

        $primaryClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $primaryClient->shouldReceive('transcribe')
            ->once()
            ->andThrow(VoiceTranscriptionException::providerUnavailable('anythingllm'));

        $fallbackClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $fallbackClient->shouldReceive('transcribe')
            ->once()
            ->andReturn(new TranscriptionResponseDTO(
                text: 'Fallback response',
                language: 'ru',
                provider: 'openai',
            ));

        $this->mockFactory->shouldReceive('create')
            ->with('anythingllm')
            ->once()
            ->andReturn($primaryClient);

        $this->mockFactory->shouldReceive('create')
            ->with('openai')
            ->once()
            ->andReturn($fallbackClient);

        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);
        $response = $service->transcribeWithFallback($request, 'anythingllm');

        $this->assertEquals('Fallback response', $response->text);
        $this->assertEquals('openai', $response->provider);
    }

    #[Test]
    public function it_throws_when_fallback_also_fails(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $request = new TranscriptionRequestDTO(audio: $audioFile, language: 'ru');

        $primaryClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $primaryClient->shouldReceive('transcribe')
            ->once()
            ->andThrow(VoiceTranscriptionException::providerUnavailable('anythingllm'));

        $fallbackClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $fallbackClient->shouldReceive('transcribe')
            ->once()
            ->andThrow(VoiceTranscriptionException::providerUnavailable('openai'));

        $this->mockFactory->shouldReceive('create')
            ->with('anythingllm')
            ->once()
            ->andReturn($primaryClient);

        $this->mockFactory->shouldReceive('create')
            ->with('openai')
            ->once()
            ->andReturn($fallbackClient);

        $this->expectException(VoiceTranscriptionException::class);

        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);
        $service->transcribeWithFallback($request, 'anythingllm');
    }

    #[Test]
    public function it_returns_providers_with_status(): void
    {
        $anythingLLMClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $anythingLLMClient->shouldReceive('isAvailable')->andReturn(true);

        $openaiClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $openaiClient->shouldReceive('isAvailable')->andReturn(false);

        $this->mockFactory->shouldReceive('create')
            ->with('anythingllm')
            ->andReturn($anythingLLMClient);

        $this->mockFactory->shouldReceive('create')
            ->with('openai')
            ->andReturn($openaiClient);

        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);
        $providers = $service->getProviders();

        $this->assertArrayHasKey('anythingllm', $providers);
        $this->assertArrayHasKey('openai', $providers);
        $this->assertTrue($providers['anythingllm']['available']);
        $this->assertFalse($providers['openai']['available']);
        $this->assertTrue($providers['openai']['is_default']);
    }

    #[Test]
    public function it_returns_available_providers(): void
    {
        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);
        $providers = $service->getAvailableProviders();

        $this->assertContains('anythingllm', $providers);
        $this->assertContains('openai', $providers);
    }

    #[Test]
    public function it_returns_default_provider(): void
    {
        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);

        $this->assertEquals('openai', $service->getDefaultProvider());
    }

    #[Test]
    public function it_checks_provider_availability(): void
    {
        $mockClient = Mockery::mock(VoiceTranscriptionInterface::class);
        $mockClient->shouldReceive('isAvailable')->andReturn(true);

        $this->mockFactory->shouldReceive('create')
            ->with('anythingllm')
            ->andReturn($mockClient);

        $service = new VoiceTranscriptionService($this->mockFactory, $this->config);

        $this->assertTrue($service->isProviderAvailable('anythingllm'));
    }

    #[Test]
    public function it_returns_false_for_disabled_provider_availability(): void
    {
        $config = new VoiceConfig(
            defaultProvider: 'anythingllm',
            maxFileSize: 25 * 1024 * 1024,
            timeout: 60,
            defaultLanguage: 'ru',
            providers: [
                'anythingllm' => ['enabled' => false],
            ],
        );

        $service = new VoiceTranscriptionService($this->mockFactory, $config);

        $this->assertFalse($service->isProviderAvailable('anythingllm'));
    }
}
