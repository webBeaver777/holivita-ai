<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\DTOs\Voice\TranscriptionResponseDTO;
use App\Exceptions\VoiceTranscriptionException;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Http\UploadedFile;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoiceControllerTest extends TestCase
{
    private VoiceTranscriptionService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(VoiceTranscriptionService::class);
        $this->app->instance(VoiceTranscriptionService::class, $this->mockService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createAudioFile(string $name = 'audio.mp3'): UploadedFile
    {
        $mp3Header = hex2bin('fff3e464').str_repeat("\0", 100);

        return UploadedFile::fake()->createWithContent($name, $mp3Header);
    }

    #[Test]
    public function it_transcribes_audio_successfully(): void
    {
        $expectedResponse = new TranscriptionResponseDTO(
            text: 'Привет, мир!',
            language: 'ru',
            confidence: 0.95,
            duration: 2.5,
            provider: 'openai',
        );

        $this->mockService->shouldReceive('transcribe')
            ->once()
            ->andReturn($expectedResponse);

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'language' => 'ru',
            'user_id' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'Привет, мир!',
                    'language' => 'ru',
                    'confidence' => 0.95,
                    'duration' => 2.5,
                    'provider' => 'openai',
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_message_when_no_speech_recognized(): void
    {
        $expectedResponse = new TranscriptionResponseDTO(
            text: '',
            language: 'ru',
            provider: 'openai',
        );

        $this->mockService->shouldReceive('transcribe')
            ->once()
            ->andReturn($expectedResponse);

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => '',
                    'message' => 'Не удалось распознать речь. Попробуйте ещё раз.',
                ],
            ]);
    }

    #[Test]
    public function it_returns_service_unavailable_on_transcription_exception(): void
    {
        $this->mockService->shouldReceive('transcribe')
            ->once()
            ->andThrow(VoiceTranscriptionException::providerUnavailable('openai'));

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
        ]);

        $response->assertServiceUnavailable()
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_validates_required_audio_file(): void
    {
        $response = $this->postJson('/api/voice/transcribe', [
            'user_id' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);
    }

    #[Test]
    public function it_validates_audio_file_format(): void
    {
        $invalidFile = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $invalidFile,
            'user_id' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);
    }

    #[Test]
    public function it_validates_required_user_id(): void
    {
        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);
    }

    #[Test]
    public function it_validates_language_format(): void
    {
        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'language' => 'russian',
            'user_id' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['language']);
    }

    #[Test]
    public function it_validates_provider_value(): void
    {
        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'provider' => 'invalid_provider',
            'user_id' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['provider']);
    }

    #[Test]
    public function it_transcribes_with_fallback_successfully(): void
    {
        $expectedResponse = new TranscriptionResponseDTO(
            text: 'Текст с fallback',
            language: 'ru',
            confidence: 0.9,
            duration: 3.0,
            provider: 'openai',
        );

        $this->mockService->shouldReceive('transcribeWithFallback')
            ->once()
            ->andReturn($expectedResponse);

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe-fallback', [
            'audio' => $audioFile,
            'user_id' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => 'Текст с fallback',
                    'provider' => 'openai',
                ],
            ]);
    }

    #[Test]
    public function it_returns_providers_with_status(): void
    {
        $this->mockService->shouldReceive('getProviders')
            ->once()
            ->andReturn([
                'openai' => [
                    'name' => 'OpenAI Whisper (Cloud)',
                    'enabled' => true,
                    'available' => true,
                    'is_default' => true,
                ],
                'anythingllm' => [
                    'name' => 'AnythingLLM (Local)',
                    'enabled' => true,
                    'available' => false,
                    'is_default' => false,
                ],
            ]);

        $this->mockService->shouldReceive('getDefaultProvider')
            ->once()
            ->andReturn('openai');

        $response = $this->getJson('/api/voice/providers');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'default' => 'openai',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'providers' => [
                        'openai' => ['name', 'enabled', 'available', 'is_default'],
                        'anythingllm' => ['name', 'enabled', 'available', 'is_default'],
                    ],
                    'default',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_valid_provider_openai(): void
    {
        $expectedResponse = new TranscriptionResponseDTO(
            text: 'Test',
            language: 'ru',
            provider: 'openai',
        );

        $this->mockService->shouldReceive('transcribe')
            ->once()
            ->andReturn($expectedResponse);

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'provider' => 'openai',
            'user_id' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'provider' => 'openai',
                ],
            ]);
    }

    #[Test]
    public function it_accepts_session_id_parameter(): void
    {
        $expectedResponse = new TranscriptionResponseDTO(
            text: 'Test',
            language: 'ru',
            provider: 'openai',
        );

        $this->mockService->shouldReceive('transcribe')
            ->once()
            ->andReturn($expectedResponse);

        $audioFile = $this->createAudioFile();
        $sessionId = '550e8400-e29b-41d4-a716-446655440000';

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
            'session_id' => $sessionId,
        ]);

        $response->assertOk();
    }

    #[Test]
    public function it_handles_empty_response_on_fallback(): void
    {
        $expectedResponse = new TranscriptionResponseDTO(
            text: '   ',
            language: 'ru',
            provider: 'openai',
        );

        $this->mockService->shouldReceive('transcribeWithFallback')
            ->once()
            ->andReturn($expectedResponse);

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe-fallback', [
            'audio' => $audioFile,
            'user_id' => 1,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'text' => '',
                    'message' => 'Не удалось распознать речь. Попробуйте ещё раз.',
                ],
            ]);
    }
}
