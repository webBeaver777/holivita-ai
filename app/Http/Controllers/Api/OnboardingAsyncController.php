<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Onboarding\OnboardingServiceInterface;
use App\Enums\MessageStatus;
use App\Http\Controllers\Concerns\JsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ChatRequest;
use App\Http\Requests\Onboarding\MessageStatusRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Контроллер для асинхронной обработки онбординга через очередь.
 */
final class OnboardingAsyncController extends Controller
{
    use JsonResponses;

    public function __construct(
        private readonly OnboardingServiceInterface $onboardingService,
    ) {}

    #[OA\Post(
        path: '/onboarding/async/chat',
        operationId: 'onboardingAsyncChat',
        summary: 'Асинхронная отправка сообщения в чат онбординга',
        description: 'Ставит сообщение пользователя в очередь для обработки AI-ассистентом. Ответ возвращается немедленно со статусом pending. Для получения результата используйте GET /onboarding/async/status. Если предыдущее сообщение ещё обрабатывается — возвращается ошибка 409. Если message пустое — инициирует начало онбординга.',
        tags: ['Onboarding Async'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', minimum: 1, example: 1, description: 'ID пользователя'),
                    new OA\Property(property: 'session_id', type: 'string', format: 'uuid', nullable: true, description: 'ID сессии (опционально, создаётся автоматически)'),
                    new OA\Property(property: 'message', type: 'string', maxLength: 2000, nullable: true, example: 'Меня зовут Иван', description: 'Текст сообщения. Если пустое — запускается приветствие ассистента.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Сообщение принято в обработку',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending'], example: 'pending'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 409,
                description: 'Предыдущее сообщение ещё обрабатывается',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Предыдущее сообщение ещё обрабатывается.'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации входных данных',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'User ID обязателен.'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
    public function chat(ChatRequest $request): JsonResponse
    {
        $session = $this->onboardingService->getOrCreateSession($request->getUserId());

        if ($this->onboardingService->hasProcessingMessages($session)) {
            return $this->conflict('Предыдущее сообщение ещё обрабатывается.');
        }

        if (empty($request->getMessage())) {
            $this->onboardingService->startOnboardingAsync($session);
        } else {
            $this->onboardingService->processMessageAsync($session, $request->getMessage());
        }

        return $this->accepted([
            'session_id' => $session->id,
            'status' => MessageStatus::PENDING->value,
        ]);
    }

    #[OA\Get(
        path: '/onboarding/async/status',
        operationId: 'onboardingAsyncStatus',
        summary: 'Проверка статуса асинхронной обработки сообщения',
        description: 'Возвращает текущий статус обработки последнего сообщения в сессии. Статусы: pending (в очереди), processing (обрабатывается), completed (готово — ответ в поле message), failed (ошибка — описание в поле error). Рекомендуется опрашивать с интервалом 1–2 секунды.',
        tags: ['Onboarding Async'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: true, description: 'ID пользователя', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
            new OA\Parameter(name: 'session_id', in: 'query', required: true, description: 'ID сессии онбординга (UUID)', schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Статус обработки получен',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed'], example: 'completed'),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: 'Здравствуйте! Расскажите о себе.', description: 'Ответ ассистента (только при status=completed)'),
                        new OA\Property(property: 'error', type: 'string', nullable: true, description: 'Описание ошибки (только при status=failed)'),
                        new OA\Property(property: 'completed', type: 'boolean', example: true, description: 'true, если обработка завершена успешно'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Сессия не найдена',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Сессия не найдена.'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'Session ID должен быть валидным UUID.'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
    public function status(MessageStatusRequest $request): JsonResponse
    {
        $session = $this->onboardingService->findSession(
            $request->getSessionId(),
            $request->getUserId(),
        );

        if (! $session) {
            return $this->notFound('Сессия не найдена.');
        }

        $status = $this->onboardingService->getMessageStatus($session);

        return $this->success([
            'session_id' => $session->id,
            'status' => $status['status'],
            'message' => $status['message'],
            'error' => $status['error'],
            'completed' => $status['status'] === MessageStatus::COMPLETED->value,
        ]);
    }
}
