<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\JsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Summary\SummaryIndexRequest;
use App\Models\OnboardingSession;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Контроллер для работы с суммаризациями.
 */
final class SummaryController extends Controller
{
    use JsonResponses;

    #[OA\Get(
        path: '/summaries',
        operationId: 'summariesIndex',
        summary: 'Список суммаризаций завершённых сессий',
        description: 'Возвращает пагинированный список суммаризаций завершённых сессий онбординга. Можно фильтровать по user_id. Суммаризация содержит структурированные данные о пользователе, собранные в ходе онбординга. Сортировка по дате завершения (новые первые).',
        tags: ['Summaries'],
        parameters: [
            new OA\Parameter(name: 'user_id', in: 'query', required: false, description: 'Фильтр по ID пользователя (если не указан — возвращаются все суммаризации)', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, description: 'Количество элементов на страницу (от 1 до 100, по умолчанию 15)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15, example: 15)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Список суммаризаций с пагинацией',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(properties: [
                                new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'summary', type: 'object', description: 'Структурированная суммаризация данных пользователя'),
                                new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', example: '2026-02-10T12:00:00.000000Z'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-02-10T11:30:00.000000Z'),
                            ], type: 'object')
                        ),
                        new OA\Property(property: 'pagination', properties: [
                            new OA\Property(property: 'current_page', type: 'integer', example: 1),
                            new OA\Property(property: 'last_page', type: 'integer', example: 3),
                            new OA\Property(property: 'per_page', type: 'integer', example: 15),
                            new OA\Property(property: 'total', type: 'integer', example: 42),
                        ], type: 'object'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 422,
                description: 'Ошибка валидации',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'errors', type: 'object'),
                ])
            ),
        ]
    )]
    public function index(SummaryIndexRequest $request): JsonResponse
    {
        $query = OnboardingSession::completed()
            ->whereNotNull('summary_json')
            ->orderByDesc('completed_at');

        if ($request->getUserId() !== null) {
            $query->forUser($request->getUserId());
        }

        $sessions = $query->paginate($request->getPerPage());

        return $this->success([
            'items' => $sessions->through(fn (OnboardingSession $session) => [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'summary' => $session->summary_json,
                'completed_at' => $session->completed_at?->toISOString(),
                'created_at' => $session->created_at?->toISOString(),
            ])->items(),
            'pagination' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'per_page' => $sessions->perPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/summaries/{sessionId}',
        operationId: 'summariesShow',
        summary: 'Получение суммаризации по ID сессии',
        description: 'Возвращает суммаризацию конкретной завершённой сессии онбординга. Суммаризация содержит структурированные данные (JSON), собранные AI-ассистентом в ходе диалога с пользователем.',
        tags: ['Summaries'],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, description: 'UUID сессии онбординга', schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Суммаризация найдена',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property(property: 'data', properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                        new OA\Property(property: 'user_id', type: 'integer', example: 1),
                        new OA\Property(property: 'summary', type: 'object', description: 'Структурированная суммаризация данных пользователя'),
                        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', example: '2026-02-10T12:00:00.000000Z'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-02-10T11:30:00.000000Z'),
                    ], type: 'object'),
                ])
            ),
            new OA\Response(
                response: 404,
                description: 'Суммаризация не найдена (сессия не существует или не завершена)',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: false),
                    new OA\Property(property: 'error', type: 'string', example: 'Суммаризация не найдена.'),
                ])
            ),
        ]
    )]
    public function show(string $sessionId): JsonResponse
    {
        $session = OnboardingSession::completed()
            ->where('id', $sessionId)
            ->first();

        if (! $session) {
            return $this->notFound('Суммаризация не найдена.');
        }

        return $this->success([
            'id' => $session->id,
            'user_id' => $session->user_id,
            'summary' => $session->summary_json,
            'completed_at' => $session->completed_at?->toISOString(),
            'created_at' => $session->created_at?->toISOString(),
        ]);
    }
}
