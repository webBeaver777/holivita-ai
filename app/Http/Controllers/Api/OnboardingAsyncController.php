<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\MessageStatus;
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
    public function __construct(
        private readonly OnboardingService $onboardingService,
    ) {}

    /**
     * POST /api/onboarding/async/chat
     *
     * Отправить сообщение в очередь на обработку.
     */
    public function chat(ChatRequest $request): JsonResponse
    {
        $session = $this->onboardingService->getOrCreateSession($request->getUserId());

        if ($this->onboardingService->hasProcessingMessages($session)) {
            return response()->json([
                'success' => false,
                'error' => 'Предыдущее сообщение ещё обрабатывается.',
            ], 409);
        }

        if (empty($request->getMessage())) {
            $this->onboardingService->startOnboardingAsync($session);
        } else {
            $this->onboardingService->processMessageAsync($session, $request->getMessage());
        }

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'status' => MessageStatus::PENDING->value,
            ],
        ], 202);
    }

    /**
     * GET /api/onboarding/async/status
     *
     * Проверить статус обработки сообщения.
     */
    public function status(MessageStatusRequest $request): JsonResponse
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

        $status = $this->onboardingService->getMessageStatus($session);

        return response()->json([
            'success' => true,
            'data' => [
                'session_id' => $session->id,
                'status' => $status['status'],
                'message' => $status['message'],
                'error' => $status['error'],
                'completed' => $status['status'] === MessageStatus::COMPLETED->value,
            ],
        ]);
    }
}
