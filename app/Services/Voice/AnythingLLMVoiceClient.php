<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Contracts\Voice\VoiceTranscriptionInterface;
use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;
use App\Exceptions\VoiceTranscriptionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент транскрипции для AnythingLLM.
 */
final class AnythingLLMVoiceClient implements VoiceTranscriptionInterface
{
    private const PROVIDER_NAME = 'anythingllm';

    private const SUPPORTED_FORMATS = [
        'audio/webm',
        'audio/wav',
        'audio/mpeg',
        'audio/mp3',
        'audio/mp4',
        'audio/ogg',
        'audio/flac',
        'video/webm', // Browser MediaRecorder often returns video/webm for audio-only
    ];

    private const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly int $timeout = 60,
    ) {}

    public function transcribe(TranscriptionRequestDTO $request): TranscriptionResponseDTO
    {
        $this->validateRequest($request);

        $endpoint = "{$this->apiUrl}/api/v1/audio/transcribe";

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Accept' => 'application/json',
                ])
                ->attach(
                    'audio',
                    file_get_contents($request->getFilePath()),
                    $request->getFileName(),
                )
                ->post($endpoint, [
                    'language' => $request->language,
                ]);

            if (! $response->successful()) {
                Log::error('AnythingLLM Voice API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new VoiceTranscriptionException(
                    "AnythingLLM transcription error: {$response->status()}",
                    self::PROVIDER_NAME,
                );
            }

            $data = $response->json();

            return new TranscriptionResponseDTO(
                text: $data['text'] ?? $data['transcription'] ?? '',
                language: $data['language'] ?? $request->language,
                confidence: $data['confidence'] ?? null,
                duration: $data['duration'] ?? null,
                provider: self::PROVIDER_NAME,
            );
        } catch (ConnectionException $e) {
            Log::error('AnythingLLM Voice connection error', ['error' => $e->getMessage()]);

            throw new VoiceTranscriptionException(
                'Could not connect to AnythingLLM voice service',
                self::PROVIDER_NAME,
                previous: $e,
            );
        }
    }

    public function isAvailable(): bool
    {
        // Проверяем наличие конфигурации
        if (empty($this->apiUrl) || empty($this->apiKey)) {
            return false;
        }

        try {
            // Проверяем авторизацию
            $response = Http::timeout(5)
                ->withHeaders(['Authorization' => "Bearer {$this->apiKey}"])
                ->get("{$this->apiUrl}/api/v1/auth");

            if (! $response->successful()) {
                Log::warning('AnythingLLM auth check failed', [
                    'status' => $response->status(),
                    'url' => $this->apiUrl,
                ]);

                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::warning('AnythingLLM availability check failed', [
                'error' => $e->getMessage(),
                'url' => $this->apiUrl,
            ]);

            return false;
        }
    }

    public function getProviderName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function getSupportedFormats(): array
    {
        return self::SUPPORTED_FORMATS;
    }

    public function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    private function validateRequest(TranscriptionRequestDTO $request): void
    {
        $mimeType = $request->getMimeType();

        if (! in_array($mimeType, self::SUPPORTED_FORMATS, true)) {
            throw VoiceTranscriptionException::unsupportedFormat($mimeType, self::PROVIDER_NAME);
        }

        if ($request->audio instanceof \Illuminate\Http\UploadedFile) {
            $size = $request->audio->getSize();

            if ($size > self::MAX_FILE_SIZE) {
                throw VoiceTranscriptionException::fileTooLarge($size, self::MAX_FILE_SIZE, self::PROVIDER_NAME);
            }
        }
    }
}
