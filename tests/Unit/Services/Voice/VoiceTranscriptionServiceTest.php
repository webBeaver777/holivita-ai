<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Voice;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;
use App\Enums\MessageStatus;
use App\Exceptions\VoiceTranscriptionException;
use App\Jobs\Voice\ProcessVoiceTranscriptionJob;
use App\Models\VoiceTranscription;
use App\Services\Voice\OpenAIVoiceClient;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoiceTranscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private OpenAIVoiceClient|MockInterface $mockClient;

    private VoiceTranscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(OpenAIVoiceClient::class);
        $this->service = new VoiceTranscriptionService(
            client: $this->mockClient,
            defaultLanguage: 'ru',
            storagePath: 'voice-uploads',
            storageDisk: 'local',
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
        $request = new TranscriptionRequestDTO(audio: $audioFile, language: 'ru');

        $expectedResponse = new TranscriptionResponseDTO(
            text: 'Привет, мир!',
            language: 'ru',
            confidence: 0.95,
            duration: 2.5,
            provider: 'openai',
        );

        $this->mockClient->shouldReceive('transcribe')
            ->once()
            ->andReturn($expectedResponse);

        $response = $this->service->transcribe($request);

        $this->assertEquals('Привет, мир!', $response->text);
        $this->assertEquals('ru', $response->language);
        $this->assertEquals(0.95, $response->confidence);
        $this->assertEquals('openai', $response->provider);
    }

    #[Test]
    public function it_rethrows_voice_transcription_exception(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $request = new TranscriptionRequestDTO(audio: $audioFile, language: 'ru');

        $this->mockClient->shouldReceive('transcribe')
            ->once()
            ->andThrow(new VoiceTranscriptionException('API error'));

        $this->expectException(VoiceTranscriptionException::class);
        $this->expectExceptionMessage('API error');

        $this->service->transcribe($request);
    }

    #[Test]
    public function it_checks_service_availability(): void
    {
        $this->mockClient->shouldReceive('isAvailable')
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->service->isAvailable());
    }

    #[Test]
    public function it_checks_service_unavailability(): void
    {
        $this->mockClient->shouldReceive('isAvailable')
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->service->isAvailable());
    }

    #[Test]
    public function it_creates_async_transcription_and_dispatches_job(): void
    {
        Storage::fake('local');
        Queue::fake();

        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');

        $transcription = $this->service->transcribeAsync(
            audio: $audioFile,
            userId: 1,
            sessionId: '550e8400-e29b-41d4-a716-446655440000',
            language: 'ru',
        );

        $this->assertDatabaseHas('voice_transcriptions', [
            'id' => $transcription->id,
            'user_id' => 1,
            'session_id' => '550e8400-e29b-41d4-a716-446655440000',
            'provider' => 'openai',
            'language' => 'ru',
            'status' => MessageStatus::PENDING->value,
        ]);

        Queue::assertPushed(ProcessVoiceTranscriptionJob::class);
        Storage::disk('local')->assertExists($transcription->stored_path);
    }

    #[Test]
    public function it_uses_default_language_when_not_specified(): void
    {
        Storage::fake('local');
        Queue::fake();

        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');

        $transcription = $this->service->transcribeAsync(
            audio: $audioFile,
            userId: 1,
        );

        $this->assertEquals('ru', $transcription->language);
    }

    #[Test]
    public function it_returns_transcription_status(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::COMPLETED,
            'transcribed_text' => 'Привет',
            'provider' => 'openai',
            'confidence' => 0.9,
            'duration' => 1.5,
        ]);

        $status = $this->service->getTranscriptionStatus($transcription);

        $this->assertEquals('completed', $status['status']);
        $this->assertEquals('Привет', $status['text']);
        $this->assertEquals('openai', $status['provider']);
        $this->assertEquals(0.9, $status['confidence']);
    }

    #[Test]
    public function it_finds_transcription_by_id_and_user(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PENDING,
        ]);

        $found = $this->service->findTranscription($transcription->id, 1);
        $this->assertNotNull($found);
        $this->assertEquals($transcription->id, $found->id);

        $notFound = $this->service->findTranscription($transcription->id, 999);
        $this->assertNull($notFound);
    }

    #[Test]
    public function it_detects_processing_transcriptions(): void
    {
        VoiceTranscription::create([
            'user_id' => 1,
            'session_id' => '550e8400-e29b-41d4-a716-446655440000',
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PROCESSING,
        ]);

        $this->assertTrue($this->service->hasProcessingTranscriptions(1, '550e8400-e29b-41d4-a716-446655440000'));
        $this->assertFalse($this->service->hasProcessingTranscriptions(999));
    }
}
