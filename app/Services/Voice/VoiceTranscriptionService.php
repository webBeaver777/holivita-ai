<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;
use App\Enums\MessageStatus;
use App\Exceptions\VoiceTranscriptionException;
use App\Jobs\Voice\ProcessVoiceTranscriptionJob;
use App\Models\VoiceTranscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Сервис транскрипции через OpenAI Whisper.
 */
class VoiceTranscriptionService
{
    public function __construct(
        private readonly OpenAIVoiceClient $client,
        private readonly string $defaultLanguage = 'ru',
        private readonly string $storagePath = 'voice-uploads',
        private readonly string $storageDisk = 'local',
    ) {}

    /**
     * Транскрибировать аудио (синхронно).
     *
     * @throws VoiceTranscriptionException
     */
    public function transcribe(TranscriptionRequestDTO $request): TranscriptionResponseDTO
    {
        Log::info('Voice transcription started', [
            'language' => $request->language,
            'session_id' => $request->sessionId,
        ]);

        try {
            $response = $this->client->transcribe($request);

            Log::info('Voice transcription completed', [
                'text_length' => strlen($response->text),
                'confidence' => $response->confidence,
            ]);

            return $response;
        } catch (VoiceTranscriptionException $e) {
            Log::error('Voice transcription failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Проверить доступность сервиса.
     */
    public function isAvailable(): bool
    {
        return $this->client->isAvailable();
    }

    /**
     * Создать асинхронную транскрипцию и отправить в очередь.
     */
    public function transcribeAsync(
        UploadedFile $audio,
        int $userId,
        ?string $sessionId = null,
        ?string $language = null,
    ): VoiceTranscription {
        $originalName = $audio->getClientOriginalName();
        $mimeType = $audio->getMimeType() ?? 'audio/webm';
        $fileSize = $audio->getSize();

        $storedPath = $this->storeAudioFile($audio);

        $transcription = VoiceTranscription::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'provider' => 'openai',
            'language' => $language ?? $this->defaultLanguage,
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ]);

        ProcessVoiceTranscriptionJob::dispatch($transcription);

        Log::info('Voice transcription queued', [
            'transcription_id' => $transcription->id,
            'user_id' => $userId,
        ]);

        return $transcription;
    }

    /**
     * Получить статус транскрипции.
     *
     * @return array{status: string, text: string|null, provider: string|null, confidence: float|null, duration: float|null, error: string|null}
     */
    public function getTranscriptionStatus(VoiceTranscription $transcription): array
    {
        return [
            'status' => $transcription->status->value,
            'text' => $transcription->transcribed_text,
            'provider' => $transcription->provider,
            'confidence' => $transcription->confidence,
            'duration' => $transcription->duration,
            'error' => $transcription->error_message,
        ];
    }

    /**
     * Найти транскрипцию по ID и user_id.
     */
    public function findTranscription(string $transcriptionId, int $userId): ?VoiceTranscription
    {
        return VoiceTranscription::where('id', $transcriptionId)
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Проверить, есть ли обрабатываемые транскрипции у пользователя.
     */
    public function hasProcessingTranscriptions(int $userId, ?string $sessionId = null): bool
    {
        $query = VoiceTranscription::where('user_id', $userId)
            ->whereIn('status', [MessageStatus::PENDING, MessageStatus::PROCESSING]);

        if ($sessionId !== null) {
            $query->where('session_id', $sessionId);
        }

        return $query->exists();
    }

    /**
     * Сохранить аудиофайл во временное хранилище.
     */
    private function storeAudioFile(UploadedFile $audio): string
    {
        $filename = Str::uuid().'.'.($audio->getClientOriginalExtension() ?: 'webm');
        $path = $this->storagePath.'/'.$filename;

        Storage::disk($this->storageDisk)->put(
            $path,
            file_get_contents($audio->getRealPath()),
        );

        return $path;
    }
}
