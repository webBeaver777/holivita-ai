<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs\Voice;

use App\DTOs\Voice\TranscriptionResponseDTO;
use App\Enums\MessageStatus;
use App\Exceptions\VoiceTranscriptionException;
use App\Jobs\Voice\ProcessVoiceTranscriptionJob;
use App\Models\VoiceTranscription;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessVoiceTranscriptionJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_processes_transcription_successfully(): void
    {
        $storedPath = 'voice-uploads/test.webm';
        Storage::disk('local')->put($storedPath, 'fake audio content');

        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'provider' => 'openai',
            'language' => 'ru',
            'original_filename' => 'audio.webm',
            'stored_path' => $storedPath,
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PENDING,
        ]);

        $mockService = Mockery::mock(VoiceTranscriptionService::class);
        $mockService->shouldReceive('transcribe')
            ->once()
            ->andReturn(new TranscriptionResponseDTO(
                text: 'Привет, мир!',
                language: 'ru',
                confidence: 0.95,
                duration: 2.5,
                provider: 'openai',
            ));

        $job = new ProcessVoiceTranscriptionJob($transcription);
        $job->handle($mockService);

        $transcription->refresh();

        $this->assertEquals(MessageStatus::COMPLETED, $transcription->status);
        $this->assertEquals('Привет, мир!', $transcription->transcribed_text);
        $this->assertEquals('openai', $transcription->provider);
        $this->assertEquals(0.95, $transcription->confidence);
        $this->assertEquals(2.5, $transcription->duration);
    }

    #[Test]
    public function it_marks_as_processing_before_transcription(): void
    {
        $storedPath = 'voice-uploads/test.webm';
        Storage::disk('local')->put($storedPath, 'fake audio content');

        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => $storedPath,
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PENDING,
        ]);

        $mockService = Mockery::mock(VoiceTranscriptionService::class);
        $mockService->shouldReceive('transcribe')
            ->once()
            ->andReturnUsing(function () use ($transcription) {
                $transcription->refresh();
                $this->assertEquals(MessageStatus::PROCESSING, $transcription->status);

                return new TranscriptionResponseDTO(
                    text: 'Test',
                    language: 'ru',
                    provider: 'openai',
                );
            });

        $job = new ProcessVoiceTranscriptionJob($transcription);
        $job->handle($mockService);
    }

    #[Test]
    public function it_cleans_up_temp_file_after_success(): void
    {
        $storedPath = 'voice-uploads/test.webm';
        Storage::disk('local')->put($storedPath, 'fake audio content');

        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => $storedPath,
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PENDING,
        ]);

        $mockService = Mockery::mock(VoiceTranscriptionService::class);
        $mockService->shouldReceive('transcribe')
            ->once()
            ->andReturn(new TranscriptionResponseDTO(
                text: 'Test',
                language: 'ru',
                provider: 'openai',
            ));

        $job = new ProcessVoiceTranscriptionJob($transcription);
        $job->handle($mockService);

        Storage::disk('local')->assertMissing($storedPath);
    }

    #[Test]
    public function it_handles_transcription_exception(): void
    {
        $storedPath = 'voice-uploads/test.webm';
        Storage::disk('local')->put($storedPath, 'fake audio content');

        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => $storedPath,
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PENDING,
        ]);

        $mockService = Mockery::mock(VoiceTranscriptionService::class);
        $mockService->shouldReceive('transcribe')
            ->once()
            ->andThrow(new VoiceTranscriptionException('API error'));

        $job = new ProcessVoiceTranscriptionJob($transcription);

        $this->expectException(VoiceTranscriptionException::class);

        try {
            $job->handle($mockService);
        } finally {
            $transcription->refresh();
            $this->assertEquals(MessageStatus::FAILED, $transcription->status);
            $this->assertNotNull($transcription->error_message);
        }
    }

    #[Test]
    public function it_handles_failed_method(): void
    {
        $storedPath = 'voice-uploads/test.webm';
        Storage::disk('local')->put($storedPath, 'fake audio content');

        $transcription = VoiceTranscription::create([
            'user_id' => 1,
            'original_filename' => 'audio.webm',
            'stored_path' => $storedPath,
            'mime_type' => 'audio/webm',
            'file_size' => 1024,
            'status' => MessageStatus::PROCESSING,
        ]);

        $job = new ProcessVoiceTranscriptionJob($transcription);
        $job->failed(new \Exception('Test error'));

        $transcription->refresh();

        $this->assertEquals(MessageStatus::FAILED, $transcription->status);
        $this->assertEquals('Test error', $transcription->error_message);
        Storage::disk('local')->assertMissing($storedPath);
    }
}
