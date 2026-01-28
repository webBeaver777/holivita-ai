<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MessageStatus;
use App\Http\Controllers\Concerns\JsonResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\ChatRequest;
use App\Http\Requests\Onboarding\MessageStatusRequest;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;

/**
 * Контроллер для асинхронной обработки онбординга через очередь.
 */
final class OnboardingAsyncController extends Controller
{
    use JsonResponses;

    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    /**
     * POST /api/onboarding/async/chat
     */
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

    /**
     * GET /api/onboarding/async/status
     */
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
