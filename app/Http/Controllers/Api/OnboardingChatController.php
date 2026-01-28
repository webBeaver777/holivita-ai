<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\AIClientException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ChatRequest;
use App\Http\Requests\Onboarding\CompleteRequest;
use App\Http\Requests\Onboarding\HistoryRequest;
use App\Http\Requests\Onboarding\ValidateUserRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер API для онбординг-чата.
 */
final class OnboardingChatController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    /**
     * POST /api/onboarding/validate-user
     */
    public function validateUser(ValidateUserRequest $request): JsonResponse
    {
        $userId = $request->getUserId();
        $result = $this->onboardingService->canStartOnboarding($userId);

        if (! $result['can_start']) {
            return response()->json([
                'success' => false,
                'message' => $result['reason'],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => 'User ID валиден',
            'user_id' => $userId,
        ]);
    }

    /**
     * POST /api/onboarding/chat
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        try {
            $session = $this->onboardingService->getOrCreateSession($request->getUserId());

            $response = empty($request->getMessage())
                ? $this->onboardingService->startOnboarding($session)
                : $this->onboardingService->processMessage($session, $request->getMessage());

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => $response->message,
                    'completed' => $response->isComplete,
                    'session_id' => $session->id,
                ],
            ]);
        } catch (AIClientException) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось получить ответ от ассистента. Попробуйте позже.',
            ], 503);
        }
    }

    /**
     * POST /api/onboarding/complete
     */
    public function complete(CompleteRequest $request): JsonResponse
    {
        $session = $this->onboardingService->findSession(
            $request->getSessionId(),
            $request->getUserId(),
        );

        if (! $session) {
            return response()->json([
                'success' => false,
                'error' => 'Сессия не найдена.',
            ], 404);
        }

        try {
            $response = $this->onboardingService->completeOnboarding($session);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $response->summary,
                    'session_id' => $session->id,
                ],
            ]);
        } catch (AIClientException) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось создать суммаризацию. Попробуйте позже.',
            ], 503);
        }
    }

    /**
     * GET /api/onboarding/history
     */
    public function history(HistoryRequest $request): JsonResponse
    {
        $session = $this->onboardingService->getLatestSession($request->getUserId());

        if (! $session) {
            return response()->json([
                'success' => true,
                'data' => [
                    'messages' => [],
                    'session_id' => null,
                    'is_completed' => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'messages' => $this->onboardingService->getConversationHistory($session),
                'session_id' => $session->id,
                'is_completed' => $session->isCompleted(),
            ],
        ]);
    }
}
