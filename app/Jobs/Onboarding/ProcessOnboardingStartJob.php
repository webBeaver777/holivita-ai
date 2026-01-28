<?php

declare(strict_types=1);

namespace App\Jobs\Onboarding;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatRequestDTO;
use App\Exceptions\AIClientException;
use App\Jobs\Concerns\ProcessesOnboardingMessages;
use App\Models\OnboardingSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job для асинхронного старта онбординга.
 */
final class ProcessOnboardingStartJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use ProcessesOnboardingMessages;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $backoff;

    public function __construct(
        public readonly OnboardingSession $session,
    ) {
        $this->initializeJobConfig();
    }

    public function handle(AIClientInterface $aiClient): void
    {
        $pendingMessage = $this->createPendingAssistantMessage($this->session);
        $pendingMessage->markAsProcessing();

        try {
            $response = $aiClient->chat(new ChatRequestDTO(
                message: $this->getWelcomePrompt(),
                sessionId: $this->session->id,
            ));

            $pendingMessage->markAsCompleted($response->message);

            Log::info('Started onboarding session via queue', [
                'session_id' => $this->session->id,
            ]);
        } catch (AIClientException $e) {
            $pendingMessage->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('Failed to start onboarding', [
            'session_id' => $this->session->id,
            'error' => $exception?->getMessage(),
        ]);

        $this->markSessionMessagesAsFailed(
            $this->session,
            $exception?->getMessage() ?? 'Unknown error'
        );
    }
}
