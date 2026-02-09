<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MessageStatus;
use App\Jobs\Voice\ProcessVoiceTranscriptionJob;
use App\Models\VoiceTranscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoiceAsyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function createAudioFile(string $name = 'audio.mp3'): UploadedFile
    {
        // MP3 файл с минимальным валидным заголовком
        $mp3Header = hex2bin('fff3e464').str_repeat("\0", 100);

        return UploadedFile::fake()->createWithContent($name, $mp3Header);
    }

    #[Test]
    public function it_queues_transcription_job(): void
    {
        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/async/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
            'language' => 'ru',
        ]);

        $response->assertAccepted()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transcription_id',
                    'status',
                ],
            ]);

        Queue::assertPushed(ProcessVoiceTranscriptionJob::class);
    }

    #[Test]
    public function it_creates_transcription_record(): void
    {
        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/async/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
            'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $response->assertAccepted();

        $transcriptionId = $response->json('data.transcription_id');

        $this->assertDatabaseHas('voice_transcriptions', [
            'id' => $transcriptionId,
            'user_id' => 1,
            'session_id' => '550e8400-e29b-41d4-a716-446655440000',
            'status' => MessageStatus::PENDING->value,
        ]);
    }

    #[Test]
    public function it_stores_audio_file(): void
    {
        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/async/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
        ]);

        $response->assertAccepted();

        $transcription = VoiceTranscription::find($response->json('data.transcription_id'));

        Storage::disk('local')->assertExists($transcription->stored_path);
    }

    #[Test]
    public function it_returns_pending_status(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PENDING,
        ]);

        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'transcription_id' => $transcription->id,
            'user_id' => 1,
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'transcription_id' => $transcription->id,
                    'status' => 'pending',
                    'completed' => false,
                ],
            ]);
    }

    #[Test]
    public function it_returns_processing_status(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PROCESSING,
        ]);

        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'transcription_id' => $transcription->id,
            'user_id' => 1,
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'processing',
                    'completed' => false,
                ],
            ]);
    }

    #[Test]
    public function it_returns_completed_status_with_text(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::COMPLETED,
            'transcribed_text' => 'Привет, мир!',
            'provider' => 'openai',
            'confidence' => 0.95,
            'duration' => 2.5,
        ]);

        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'transcription_id' => $transcription->id,
            'user_id' => 1,
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'completed' => true,
                    'text' => 'Привет, мир!',
                    'provider' => 'openai',
                    'confidence' => 0.95,
                    'duration' => 2.5,
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_message_when_no_speech_recognized(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::COMPLETED,
            'transcribed_text' => '',
            'provider' => 'openai',
        ]);

        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'transcription_id' => $transcription->id,
            'user_id' => 1,
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'completed' => true,
                    'text' => '',
                    'message' => 'Не удалось распознать речь. Попробуйте ещё раз.',
                ],
            ]);
    }

    #[Test]
    public function it_returns_failed_status_with_error(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::FAILED,
            'error_message' => 'Provider unavailable',
        ]);

        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'transcription_id' => $transcription->id,
            'user_id' => 1,
        ]));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'failed',
                    'completed' => true,
                    'error' => 'Provider unavailable',
                ],
            ]);
    }

    #[Test]
    public function it_returns_not_found_for_invalid_transcription(): void
    {
        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'transcription_id' => '550e8400-e29b-41d4-a716-446655440000',
            'user_id' => 1,
        ]));

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_not_found_for_wrong_user(): void
    {
        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => 'voice-uploads/test.webm',
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PENDING,
        ]);

        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'transcription_id' => $transcription->id,
            'user_id' => 999,
        ]));

        $response->assertNotFound();
    }

    #[Test]
    public function it_prevents_concurrent_transcriptions(): void
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

        $audioFile = $this->createAudioFile();

        $response = $this->postJson('/api/voice/async/transcribe', [
            'audio' => $audioFile,
            'user_id' => 1,
            'session_id' => '550e8400-e29b-41d4-a716-446655440000',
        ]);

        $response->assertConflict();
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $response = $this->postJson('/api/voice/async/transcribe', [
            'user_id' => 1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);
    }

    #[Test]
    public function it_validates_status_request(): void
    {
        $response = $this->getJson('/api/voice/async/status?'.http_build_query([
            'user_id' => 1,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['transcription_id']);
    }
}
