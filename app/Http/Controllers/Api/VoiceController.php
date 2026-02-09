<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\Exceptions\VoiceTranscriptionException;
use App\Http\Controllers\Concerns\JsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Voice\TranscribeRequest;
use App\Http\Requests\Voice\TranscriptionStatusRequest;
use App\Services\Voice\VoiceTranscriptionService;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер для голосового ввода.
 */
final class VoiceController extends Controller
{
    use JsonResponses;

    public function __construct(
        private readonly VoiceTranscriptionService $transcriptionService,
    ) {}

    /**
     * POST /api/voice/transcribe
     * Транскрибировать аудио в текст.
     */
    public function transcribe(TranscribeRequest $request): JsonResponse
    {
        try {
            $dto = new TranscriptionRequestDTO(
                audio: $request->getAudioFile(),
                language: $request->getLanguage(),
                sessionId: $request->getSessionId(),
            );

            $response = $this->transcriptionService->transcribe($dto);

            if ($response->isEmpty()) {
                return $this->success([
                    'text' => '',
                    'message' => 'Не удалось распознать речь. Попробуйте ещё раз.',
                    'provider' => $response->provider,
                ]);
            }

            return $this->success([
                'text' => $response->text,
                'language' => $response->language,
                'confidence' => $response->confidence,
                'duration' => $response->duration,
                'provider' => $response->provider,
            ]);
        } catch (VoiceTranscriptionException $e) {
            return $this->serviceUnavailable($e->getMessage());
        }
    }

    /**
     * GET /api/voice/status
     * Проверить доступность сервиса.
     */
    public function status(): JsonResponse
    {
        return $this->success([
            'available' => $this->transcriptionService->isAvailable(),
            'provider' => 'openai',
        ]);
    }

    // === ASYNC ENDPOINTS ===

    /**
     * POST /api/voice/async/transcribe
     * Отправить аудио на асинхронную транскрипцию.
     */
    public function transcribeAsync(TranscribeRequest $request): JsonResponse
    {
        if ($this->transcriptionService->hasProcessingTranscriptions(
            $request->getUserId(),
            $request->getSessionId(),
        )) {
            return $this->conflict('Предыдущая транскрипция ещё обрабатывается.');
        }

        $transcription = $this->transcriptionService->transcribeAsync(
            audio: $request->getAudioFile(),
            userId: $request->getUserId(),
            sessionId: $request->getSessionId(),
            language: $request->getLanguage(),
        );

        return $this->accepted([
            'transcription_id' => $transcription->id,
            'status' => 'pending',
        ]);
    }

    /**
     * GET /api/voice/async/status
     * Получить статус асинхронной транскрипции.
     */
    public function transcriptionStatus(TranscriptionStatusRequest $request): JsonResponse
    {
        $transcription = $this->transcriptionService->findTranscription(
            $request->getTranscriptionId(),
            $request->getUserId(),
        );

        if (! $transcription) {
            return $this->notFound('Транскрипция не найдена.');
        }

        $status = $this->transcriptionService->getTranscriptionStatus($transcription);

        $data = [
            'transcription_id' => $transcription->id,
            'status' => $status['status'],
            'completed' => $transcription->isFinished(),
        ];

        if ($transcription->isCompleted()) {
            $text = trim($status['text'] ?? '');

            $data['text'] = $text;
            $data['provider'] = $status['provider'];
            $data['confidence'] = $status['confidence'];
            $data['duration'] = $status['duration'];

            if (empty($text)) {
                $data['message'] = 'Не удалось распознать речь. Попробуйте ещё раз.';
            }
        } elseif ($transcription->isFailed()) {
            $data['error'] = $status['error'];
        }

        return $this->success($data);
    }
}
