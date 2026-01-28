<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OnboardingSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Контроллер для работы с суммаризациями.
 */
final class SummaryController extends Controller
{
    /**
     * GET /api/summaries
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = OnboardingSession::completed()
            ->whereNotNull('summary_json')
            ->orderByDesc('completed_at');

        if ($request->filled('user_id')) {
            $query->forUser($request->integer('user_id'));
        }

        $sessions = $query->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $sessions->through(fn (OnboardingSession $session) => [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'summary' => $session->summary_json,
                'completed_at' => $session->completed_at?->toISOString(),
                'created_at' => $session->created_at?->toISOString(),
            ]),
            'meta' => [
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
            return response()->json([
                'success' => false,
                'error' => 'Суммаризация не найдена.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'summary' => $session->summary_json,
                'completed_at' => $session->completed_at?->toISOString(),
                'created_at' => $session->created_at?->toISOString(),
            ],
        ]);
    }
}
