<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\Exceptions\VoiceTranscriptionException;
use App\Models\VoiceTranscription;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Job для асинхронной транскрипции голосовых сообщений.
 */
final class ProcessVoiceTranscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public function __construct(
        public readonly VoiceTranscription $transcription,
    ) {
        $this->tries = (int) config('ai.onboarding.job_tries', 3);
        $this->backoff = (int) config('ai.onboarding.job_backoff', 10);
        $this->onQueue((string) config('ai.onboarding.queue', 'onboarding'));
    }

    public function handle(VoiceTranscriptionService $service): void
    {
        $this->transcription->markAsProcessing();

        Log::info('Processing voice transcription', [
            'transcription_id' => $this->transcription->id,
            'user_id' => $this->transcription->user_id,
        ]);

        try {
            $request = new TranscriptionRequestDTO(
                audio: $this->transcription->getFullStoredPath(),
                language: $this->transcription->language,
                sessionId: $this->transcription->session_id,
                mimeType: $this->transcription->mime_type,
            );

            $response = $service->transcribe($request);

            $this->transcription->markAsCompleted(
                text: $response->text,
                provider: $response->provider,
                confidence: $response->confidence,
                duration: $response->duration,
            );

            Log::info('Voice transcription completed', [
                'transcription_id' => $this->transcription->id,
                'text_length' => strlen($response->text),
            ]);
        } catch (VoiceTranscriptionException $e) {
            $this->transcription->markAsFailed($e->getMessage());
            throw $e;
        } finally {
            $this->cleanupTempFile($this->transcription->stored_path);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $error = $exception?->getMessage() ?? 'Unknown error';

        Log::error('Voice transcription job failed', [
            'transcription_id' => $this->transcription->id,
            'user_id' => $this->transcription->user_id,
            'error' => $error,
        ]);

        $this->transcription->markAsFailed($error);
        $this->cleanupTempFile($this->transcription->stored_path);
    }

    private function cleanupTempFile(string $path): void
    {
        $disk = Storage::disk((string) config('voice.storage_disk', 'local'));

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }
}
