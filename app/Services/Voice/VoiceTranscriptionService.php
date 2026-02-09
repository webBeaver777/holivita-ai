<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;
use App\DTOs\Voice\VoiceConfig;
use App\Enums\MessageStatus;
use App\Exceptions\VoiceTranscriptionException;
use App\Jobs\Voice\ProcessVoiceTranscriptionJob;
use App\Models\VoiceTranscription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Сервис транскрипции через OpenAI Whisper.
 */
class VoiceTranscriptionService
{
    private readonly VoiceConfig $config;

    private ?OpenAIVoiceClient $client = null;

    public function __construct(
        private readonly VoiceClientFactory $factory,
        ?VoiceConfig $config = null,
    ) {
        $this->config = $config ?? VoiceConfig::fromConfig();
    }

    /**
     * Транскрибировать аудио.
     *
     * @throws VoiceTranscriptionException
     */
    public function transcribe(TranscriptionRequestDTO $request): TranscriptionResponseDTO
    {
        $client = $this->getClient();

        Log::info('Voice transcription started', [
            'provider' => 'openai',
            'language' => $request->language,
            'session_id' => $request->sessionId,
        ]);

        try {
            $response = $client->transcribe($request);

            Log::info('Voice transcription completed', [
                'provider' => 'openai',
                'text_length' => strlen($response->text),
                'confidence' => $response->confidence,
            ]);

            return $response;
        } catch (VoiceTranscriptionException $e) {
            Log::error('Voice transcription failed', [
                'provider' => 'openai',
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
        return $this->config->isConfigured() && $this->getClient()->isAvailable();
    }

    /**
     * Получить клиента.
     */
    private function getClient(): OpenAIVoiceClient
    {
        if ($this->client === null) {
            $this->client = $this->factory->create();
        }

        return $this->client;
    }

    // === ASYNC METHODS ===

    /**
     * Создать асинхронную транскрипцию и отправить в очередь.
     */
    public function transcribeAsync(
        UploadedFile $audio,
        int $userId,
        ?string $sessionId = null,
        ?string $language = null,
    ): VoiceTranscription {
        // Собираем данные о файле ДО перемещения
        $originalName = $audio->getClientOriginalName();
        $mimeType = $audio->getMimeType() ?? 'audio/webm';
        $fileSize = $audio->getSize();

        $storedPath = $this->storeAudioFile($audio);

        $transcription = VoiceTranscription::create([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'provider' => 'openai',
            'language' => $language ?? $this->config->defaultLanguage,
            'original_filename' => $originalName,
            'stored_path' => $storedPath,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
        ]);

        ProcessVoiceTranscriptionJob::dispatch($transcription);

        Log::info('Voice transcription queued', [
            'transcription_id' => $transcription->id,
            'user_id' => $userId,
            'provider' => 'openai',
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
        $directory = storage_path('app/'.$this->config->storagePath);
        $fullPath = $directory.'/'.$filename;

        // Создаём директорию если не существует
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Перемещаем файл
        $audio->move($directory, $filename);

        if (! file_exists($fullPath)) {
            throw new \RuntimeException('Failed to store audio file: '.$fullPath);
        }

        return $this->config->storagePath.'/'.$filename;
    }
}
