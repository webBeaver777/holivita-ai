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
use OpenApi\Attributes as OA;

/**
 * Контроллер для голосового ввода.
 */
final class VoiceController extends Controller
{
    use JsonResponses;

    public function __construct(
        private readonly VoiceTranscriptionService $transcriptionService,
    ) {}

    #[OA\Post(
        path: '/voice/transcribe',
        operationId: 'voiceTranscribe',
        summary: 'Синхронная транскрипция аудио в текст',
        description: 'Отправляет аудиофайл на транскрипцию через OpenAI Whisper API и возвращает распознанный текст синхронно. Поддерживаемые форматы: webm, wav, mp3, mp4, ogg, flac. Максимальный размер файла — 25 МБ. Если речь не распознана — возвращает пустой текст с сообщением.',
        tags: ['Voice'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['audio', 'user_id'],
                    properties: [
                        new OA\Property(property: 'audio', type: 'string', format: 'binary', description: 'Аудиофайл (webm, wav, mp3, mp4, ogg, flac). Макс. 25 МБ.'),
                        new OA\Property(property: 'user_id', type: 'integer', minimum: 1, example: 1, description: 'ID пользователя'),
                        new OA\Property(property: 'language', type: 'string', maxLength: 2, example: 'ru', description: 'Код языка ISO 639-1 (по умолчанию: ru)'),
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', description: 'ID сессии онбординга (опционально, для привязки к сессии)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Транскрипция выполнена успешно',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'text', type: 'string', example: 'Меня зовут Иван, мне 35 лет', description: 'Распознанный текст (пустая строка, если речь не распознана)'),
                        new OA\Property(property: 'language', type: 'string', example: 'ru', description: 'Определённый язык аудио'),
                        new OA\Property(property: 'confidence', type: 'number', format: 'float', example: 0.95, nullable: true, description: 'Уровень уверенности распознавания (0–1)'),
                        new OA\Property(property: 'duration', type: 'number', format: 'float', example: 3.5, nullable: true, description: 'Длительность аудио в секундах'),
                        new OA\Property(property: 'provider', type: 'string', example: 'openai', description: 'Провайдер транскрипции'),
                        new OA\Property(property: 'message', type: 'string', example: 'Не удалось распознать речь. Попробуйте ещё раз.', description: 'Сообщение (только если текст пустой)'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации (неверный формат, превышен размер и т.д.)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Аудио файл обязателен.'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
            new OA\Response(
                response: 503,
                description: 'Сервис транскрипции недоступен',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Сервис транскрипции временно недоступен.'),
                ])
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/voice/status',
        operationId: 'voiceStatus',
        summary: 'Проверка доступности сервиса транскрипции',
        description: 'Возвращает информацию о доступности сервиса голосовой транскрипции. Проверяет наличие API-ключа и работоспособность провайдера (OpenAI Whisper). Используйте перед отправкой аудио для проверки готовности сервиса.',
        tags: ['Voice'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Статус сервиса',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'available', type: 'boolean', example: true, description: 'true, если сервис доступен и настроен'),
                        new OA\Property(property: 'provider', type: 'string', example: 'openai', description: 'Текущий провайдер транскрипции'),
                    ], type: 'object'),
                ])
            ),
        ]
    )]
    public function status(): JsonResponse
    {
        return $this->success([
            'available' => $this->transcriptionService->isAvailable(),
            'provider' => 'openai',
        ]);
    }

    // === ASYNC ENDPOINTS ===

    #[OA\Post(
        path: '/voice/async/transcribe',
        operationId: 'voiceAsyncTranscribe',
        summary: 'Асинхронная транскрипция аудио',
        description: 'Отправляет аудиофайл в очередь для асинхронной транскрипции. Аудио сохраняется на диск, и задача ставится в очередь. Ответ возвращается немедленно с ID транскрипции и статусом pending. Для получения результата используйте GET /voice/async/status. Если предыдущая транскрипция ещё обрабатывается — возвращается ошибка 409.',
        tags: ['Voice'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['audio', 'user_id'],
                    properties: [
                        new OA\Property(property: 'audio', type: 'string', format: 'binary', description: 'Аудиофайл (webm, wav, mp3, mp4, ogg, flac). Макс. 25 МБ.'),
                        new OA\Property(property: 'user_id', type: 'integer', minimum: 1, example: 1, description: 'ID пользователя'),
                        new OA\Property(property: 'language', type: 'string', maxLength: 2, example: 'ru', description: 'Код языка ISO 639-1 (по умолчанию: ru)'),
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', description: 'ID сессии онбординга (опционально)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Аудио принято в обработку',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'transcription_id', type: 'string', format: 'uuid', example: '660e8400-e29b-41d4-a716-446655440000', description: 'ID транскрипции для отслеживания статуса'),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending'], example: 'pending'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 409,
                description: 'Предыдущая транскрипция ещё обрабатывается',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Предыдущая транскрипция ещё обрабатывается.'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Аудио файл обязателен.'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
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

    #[OA\Get(
        path: '/voice/async/status',
        operationId: 'voiceAsyncStatus',
        summary: 'Статус асинхронной транскрипции',
        description: 'Возвращает текущий статус асинхронной транскрипции. Статусы: pending (в очереди), processing (обрабатывается), completed (готово — текст в поле text), failed (ошибка в поле error). При успешном завершении возвращаются также provider, confidence и duration. Рекомендуется опрашивать с интервалом 1–2 секунды.',
        tags: ['Voice'],
        parameters: [
            new OA\Parameter(name: 'transcription_id', in: 'query', required: true, description: 'UUID транскрипции, полученный при отправке аудио', schema: new OA\Schema(type: 'string', format: 'uuid', example: '660e8400-e29b-41d4-a716-446655440000')),
            new OA\Parameter(name: 'user_id', in: 'query', required: true, description: 'ID пользователя', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Статус транскрипции',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'transcription_id', type: 'string', format: 'uuid', example: '660e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed'], example: 'completed'),
                        new OA\Property(property: 'completed', type: 'boolean', example: true, description: 'true, если транскрипция завершена (успешно или с ошибкой)'),
                        new OA\Property(property: 'text', type: 'string', example: 'Меня зовут Иван', description: 'Распознанный текст (только при status=completed)'),
                        new OA\Property(property: 'provider', type: 'string', example: 'openai', description: 'Провайдер (только при status=completed)'),
                        new OA\Property(property: 'confidence', type: 'number', format: 'float', example: 0.95, nullable: true, description: 'Уверенность (только при status=completed)'),
                        new OA\Property(property: 'duration', type: 'number', format: 'float', example: 3.5, nullable: true, description: 'Длительность аудио в секундах (только при status=completed)'),
                        new OA\Property(property: 'message', type: 'string', example: 'Не удалось распознать речь. Попробуйте ещё раз.', description: 'Сообщение при пустом тексте'),
                        new OA\Property(property: 'error', type: 'string', description: 'Описание ошибки (только при status=failed)'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Транскрипция не найдена',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Транскрипция не найдена.'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'ID транскрипции обязателен.'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
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
