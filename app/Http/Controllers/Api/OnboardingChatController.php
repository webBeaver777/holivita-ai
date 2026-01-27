<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\AIClientException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ChatRequest;
use App\Http\Requests\Onboarding\CompleteRequest;
use App\Http\Requests\Onboarding\ValidateUserRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Контроллер API для онбординг-чата.
 */
final class OnboardingChatController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    /**
     * Валидация user_id перед началом онбординга.
     *
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
            ], Response::HTTP_CONFLICT);
        }

        return response()->json([
            'success' => true,
            'message' => 'User ID валиден',
            'user_id' => $userId,
        ]);
    }

    /**
     * Отправить сообщение в чат.
     *
     * POST /api/onboarding/chat
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        $userId = $request->getUserId();
        $message = $request->getMessage();

        try {
            $session = $this->onboardingService->getOrCreateSession($userId);

            $response = empty($message)
                ? $this->onboardingService->startOnboarding($session)
                : $this->onboardingService->processMessage($session, $message);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => $response->message,
                    'completed' => $response->isComplete,
                    'session_id' => $session->id,
                ],
            ]);
        } catch (AIClientException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось получить ответ от ассистента. Попробуйте позже.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Завершить онбординг и получить суммаризацию.
     *
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
            ], Response::HTTP_NOT_FOUND);
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
        } catch (AIClientException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Не удалось создать суммаризацию. Попробуйте позже.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    /**
     * Получить историю чата сессии.
     *
     * GET /api/onboarding/history
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
        ]);

        $userId = (int) $request->input('user_id');
        $session = $this->onboardingService->getLatestSession($userId);

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
