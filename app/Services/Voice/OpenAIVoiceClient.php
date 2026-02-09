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
 * Клиент транскрипции для OpenAI Whisper API.
 */
final class OpenAIVoiceClient implements VoiceTranscriptionInterface
{
    private const PROVIDER_NAME = 'openai';

    private const API_URL = 'https://api.openai.com/v1/audio/transcriptions';

    private const SUPPORTED_FORMATS = [
        'audio/flac',
        'audio/mp3',
        'audio/mpeg',
        'audio/mp4',
        'audio/mpga',
        'audio/ogg',
        'audio/wav',
        'audio/webm',
        'video/webm', // Browser MediaRecorder often returns video/webm for audio-only
    ];

    private const MAX_FILE_SIZE = 25 * 1024 * 1024; // 25MB

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'whisper-1',
        private readonly int $timeout = 60,
    ) {}

    public function transcribe(TranscriptionRequestDTO $request): TranscriptionResponseDTO
    {
        $this->validateRequest($request);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                ])
                ->attach(
                    'file',
                    file_get_contents($request->getFilePath()),
                    $request->getFileName(),
                )
                ->post(self::API_URL, [
                    'model' => $this->model,
                    'language' => $request->language,
                    'response_format' => 'verbose_json',
                ]);

            if (! $response->successful()) {
                Log::error('OpenAI Whisper API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new VoiceTranscriptionException(
                    "OpenAI transcription error: {$response->status()}",
                    self::PROVIDER_NAME,
                );
            }

            $data = $response->json();

            return new TranscriptionResponseDTO(
                text: $data['text'] ?? '',
                language: $data['language'] ?? $request->language,
                confidence: $this->calculateAverageConfidence($data['segments'] ?? []),
                duration: $data['duration'] ?? null,
                provider: self::PROVIDER_NAME,
            );
        } catch (ConnectionException $e) {
            Log::error('OpenAI Whisper connection error', ['error' => $e->getMessage()]);

            throw new VoiceTranscriptionException(
                'Could not connect to OpenAI Whisper service',
                self::PROVIDER_NAME,
                previous: $e,
            );
        }
    }

    public function isAvailable(): bool
    {
        return ! empty($this->apiKey);
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

    /**
     * Вычислить среднюю уверенность из сегментов.
     *
     * @param  array<array{avg_logprob?: float}>  $segments
     */
    private function calculateAverageConfidence(array $segments): ?float
    {
        if (empty($segments)) {
            return null;
        }

        $logprobs = array_filter(array_column($segments, 'avg_logprob'));

        if (empty($logprobs)) {
            return null;
        }

        // Convert log probability to confidence (0-1 scale)
        $avgLogprob = array_sum($logprobs) / count($logprobs);

        return round(exp($avgLogprob), 4);
    }
}
