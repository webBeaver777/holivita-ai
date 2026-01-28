<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\JsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Summary\SummaryIndexRequest;
use App\Models\OnboardingSession;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер для работы с суммаризациями.
 */
final class SummaryController extends Controller
{
    use JsonResponses;

    /**
     * GET /api/summaries
     */
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

    /**
     * GET /api/summaries/{sessionId}
     */
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
