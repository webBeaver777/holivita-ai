<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\AIClientException;
use App\Http\Controllers\Concerns\JsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CancelRequest;
use App\Http\Requests\Onboarding\ChatRequest;
use App\Http\Requests\Onboarding\CompleteRequest;
use App\Http\Requests\Onboarding\HistoryRequest;
use App\Http\Requests\Onboarding\ValidateUserRequest;
use App\Contracts\Onboarding\OnboardingServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер API для онбординг-чата.
 */
final class OnboardingChatController extends Controller
{
    use JsonResponses;

    public function __construct(
        private readonly OnboardingServiceInterface $onboardingService,
    ) {}

    /**
     * POST /api/onboarding/validate-user
     */
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

            return $this->success([
                'message' => $response->message,
                'completed' => $response->isComplete,
                'session_id' => $session->id,
            ]);
        } catch (AIClientException) {
            return $this->serviceUnavailable('Не удалось получить ответ от ассистента. Попробуйте позже.');
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

    /**
     * POST /api/onboarding/cancel
     */
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

    /**
     * GET /api/onboarding/history
     */
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
