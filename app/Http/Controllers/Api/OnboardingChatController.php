<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\Onboarding\OnboardingServiceInterface;
use App\Exceptions\AIClientException;
use App\Http\Controllers\Concerns\JsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CancelRequest;
use App\Http\Requests\Onboarding\ChatRequest;
use App\Http\Requests\Onboarding\CompleteRequest;
use App\Http\Requests\Onboarding\HistoryRequest;
use App\Http\Requests\Onboarding\ValidateUserRequest;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Контроллер API для онбординг-чата.
 */
final class OnboardingChatController extends Controller
{
    use JsonResponses;

    public function __construct(
        private readonly OnboardingServiceInterface $onboardingService,
    ) {}

    #[OA\Post(
        path: '/onboarding/validate-user',
        operationId: 'validateUser',
        summary: 'Валидация пользователя перед началом онбординга',
        description: 'Проверяет, может ли пользователь начать новую сессию онбординга. Если у пользователя уже есть активная сессия — возвращается конфликт (409) с ID существующей сессии. Используйте этот эндпоинт перед вызовом /onboarding/chat для проверки доступности.',
        tags: ['Onboarding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', minimum: 1, example: 1, description: 'ID пользователя'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Пользователь валиден, можно начинать онбординг',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'User ID валиден'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'user_id', type: 'integer', example: 1),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 409,
                description: 'У пользователя уже есть активная сессия',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'У пользователя уже есть активная сессия.'),
                    new OA\Property(property: 'active_session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
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
    public function validateUser(ValidateUserRequest $request): JsonResponse
    {
        $userId = $request->getUserId();
        $result = $this->onboardingService->canStartOnboarding($userId);

        if (! $result['can_start']) {
            return $this->conflict($result['reason'], [
                'active_session_id' => $result['active_session_id'],
            ]);
        }

        return $this->success([
            'user_id' => $userId,
        ], 'User ID валиден');
    }

    #[OA\Post(
        path: '/onboarding/chat',
        operationId: 'onboardingChat',
        summary: 'Отправка сообщения в синхронный чат онбординга',
        description: 'Отправляет сообщение пользователя AI-ассистенту и возвращает ответ синхронно. Если message пустое или не передано — инициирует начало онбординга (приветственное сообщение от ассистента). Если у пользователя нет активной сессии — она создаётся автоматически.',
        tags: ['Onboarding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', minimum: 1, example: 1, description: 'ID пользователя'),
                    new OA\Property(property: 'session_id', type: 'string', format: 'uuid', nullable: true, description: 'ID сессии (опционально, создаётся автоматически)'),
                    new OA\Property(property: 'message', type: 'string', maxLength: 2000, nullable: true, example: 'Меня зовут Иван, мне 35 лет', description: 'Текст сообщения. Если пустое — запускается приветствие ассистента.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ответ ассистента получен успешно',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Здравствуйте! Расскажите о ваших целях здоровья.'),
                        new OA\Property(property: 'completed', type: 'boolean', example: false, description: 'true, если ассистент считает онбординг завершённым'),
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                    ], type: 'object'),
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
            new OA\Response(
                response: 503,
                description: 'AI-сервис временно недоступен',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Не удалось получить ответ от ассистента. Попробуйте позже.'),
                ])
            ),
        ]
    )]
    public function chat(ChatRequest $request): JsonResponse
    {
        try {
            $session = $this->onboardingService->getOrCreateSession($request->getUserId());

            $response = empty($request->getMessage())
                ? $this->onboardingService->startOnboarding($session)
                : $this->onboardingService->processMessage($session, $request->getMessage());

            return $this->success([
                'message' => $response->message,
                'completed' => $response->isComplete,
                'session_id' => $session->id,
            ]);
        } catch (AIClientException) {
            return $this->serviceUnavailable('Не удалось получить ответ от ассистента. Попробуйте позже.');
        }
    }

    #[OA\Post(
        path: '/onboarding/complete',
        operationId: 'onboardingComplete',
        summary: 'Завершение онбординга и создание суммаризации',
        description: 'Завершает указанную сессию онбординга. AI-ассистент анализирует всю историю диалога и формирует структурированную суммаризацию (JSON) с данными о пользователе. Сессия переходит в статус completed. После завершения суммаризация доступна через эндпоинты /summaries.',
        tags: ['Onboarding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id', 'session_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', minimum: 1, example: 1, description: 'ID пользователя'),
                    new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000', description: 'ID сессии онбординга'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Онбординг завершён, суммаризация создана',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'summary', type: 'object', description: 'Структурированная суммаризация данных пользователя в формате JSON'),
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
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
                response: 503,
                description: 'AI-сервис временно недоступен',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Не удалось создать суммаризацию. Попробуйте позже.'),
                ])
            ),
        ]
    )]
    public function complete(CompleteRequest $request): JsonResponse
    {
        $session = $this->onboardingService->findSession(
            $request->getSessionId(),
            $request->getUserId(),
        );

        if (! $session) {
            return $this->notFound('Сессия не найдена.');
        }

        try {
            $response = $this->onboardingService->completeOnboarding($session);

            return $this->success([
                'summary' => $response->summary,
                'session_id' => $session->id,
            ]);
        } catch (AIClientException) {
            return $this->serviceUnavailable('Не удалось создать суммаризацию. Попробуйте позже.');
        }
    }

    #[OA\Post(
        path: '/onboarding/cancel',
        operationId: 'onboardingCancel',
        summary: 'Отмена сессии онбординга',
        description: 'Отменяет активную сессию онбординга пользователя. Если session_id не передан — отменяет текущую активную сессию. Сессия переходит в статус cancelled. Повторная отмена уже завершённой сессии возвращает ошибку 409.',
        tags: ['Onboarding'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['user_id'],
                properties: [
                    new OA\Property(property: 'user_id', type: 'integer', minimum: 1, example: 1, description: 'ID пользователя'),
                    new OA\Property(property: 'session_id', type: 'string', format: 'uuid', nullable: true, description: 'ID сессии (опционально — если не указан, отменяется текущая активная сессия)'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Сессия успешно отменена',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'message', type: 'string', example: 'Сессия отменена.'),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Активная сессия не найдена',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Активная сессия не найдена.'),
                ])
            ),
            new OA\Response(
                response: 409,
                description: 'Сессия уже завершена или отменена',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Сессия уже завершена.'),
                ])
            ),
        ]
    )]
    public function cancel(CancelRequest $request): JsonResponse
    {
        $session = $request->getSessionId()
            ? $this->onboardingService->findSession($request->getSessionId(), $request->getUserId())
            : $this->onboardingService->getActiveSession($request->getUserId());

        if (! $session) {
            return $this->notFound('Активная сессия не найдена.');
        }

        if (! $session->isActive()) {
            return $this->conflict('Сессия уже завершена.');
        }

        $this->onboardingService->cancelSession($session);

        return $this->success([
            'session_id' => $session->id,
        ], 'Сессия отменена.');
    }

    #[OA\Get(
        path: '/onboarding/history',
        operationId: 'onboardingHistory',
        summary: 'Получение истории чата онбординга',
        description: 'Возвращает полную историю сообщений последней сессии онбординга пользователя. Включает сообщения как пользователя (user), так и ассистента (assistant). Если у пользователя нет сессий — возвращает пустой массив.',
        tags: ['Onboarding'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: true, description: 'ID пользователя', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'История сообщений',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(
                            property: 'messages',
                            type: 'array',
                            items: new OA\Items(properties: [
                                new OA\Property(property: 'role', type: 'string', enum: ['user', 'assistant'], example: 'assistant'),
                                new OA\Property(property: 'content', type: 'string', example: 'Здравствуйте! Расскажите о себе.'),
                            ], type: 'object'),
                            description: 'Массив сообщений диалога'
                        ),
                        new OA\Property(property: 'session_id', type: 'string', format: 'uuid', nullable: true, example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'is_completed', type: 'boolean', example: false, description: 'true, если сессия завершена'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string', example: 'User ID обязателен.'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
    public function history(HistoryRequest $request): JsonResponse
    {
        $session = $this->onboardingService->getLatestSession($request->getUserId());

        if (! $session) {
            return $this->success([
                'messages' => [],
                'session_id' => null,
                'is_completed' => false,
            ]);
        }

        return $this->success([
            'messages' => $this->onboardingService->getConversationHistory($session),
            'session_id' => $session->id,
            'is_completed' => $session->isCompleted(),
        ]);
    }
}
