<?php

declare(strict_types=1);

namespace App\Jobs\Onboarding;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatRequestDTO;
use App\Exceptions\AIClientException;
use App\Jobs\Concerns\ProcessesOnboardingMessages;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job для асинхронной обработки сообщений онбординга.
 */
final class ProcessOnboardingMessageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use ProcessesOnboardingMessages;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public function __construct(
        public readonly OnboardingMessage $userMessage,
        public readonly OnboardingSession $session,
    ) {
        $this->tries = config('ai.onboarding.job_tries', 3);
        $this->backoff = config('ai.onboarding.job_backoff', 10);
        $this->onQueue($this->getQueueName());
    }

    public function handle(AIClientInterface $aiClient): void
    {
        $pendingMessage = $this->createPendingAssistantMessage($this->session);
        $pendingMessage->markAsProcessing();

        try {
            $response = $aiClient->chat(new ChatRequestDTO(
                message: $this->userMessage->content,
                sessionId: $this->session->id,
            ));

            $pendingMessage->markAsCompleted($response->message);
            $this->userMessage->markAsCompleted();

            Log::info('Processed onboarding message via queue', [
                'session_id' => $this->session->id,
                'message_id' => $this->userMessage->id,
                'is_complete' => $response->isComplete,
            ]);
        } catch (AIClientException $e) {
            $pendingMessage->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $error = $exception?->getMessage() ?? 'Unknown error';

        Log::error('Failed to process onboarding message', [
            'session_id' => $this->session->id,
            'message_id' => $this->userMessage->id,
            'error' => $error,
        ]);

        $this->userMessage->markAsFailed($error);
        $this->markSessionMessagesAsFailed($this->session, $error);
    }
}
