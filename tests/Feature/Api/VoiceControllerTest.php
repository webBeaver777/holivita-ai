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
            ->andThrow(new VoiceTranscriptionException('OpenAI unavailable'));

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
        ]);

        $response->assertServiceUnavailable()
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function it_returns_service_status(): void
    {
        $this->mockService->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $response = $this->getJson('/api/voice/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'available' => true,
                    'provider' => 'openai',
                ],
            ]);
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

        $response = $this->postJson('/api/voice/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
            'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $response->assertOk();
    }
}
