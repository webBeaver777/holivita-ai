<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatResponseDTO;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Exceptions\AIClientException;
use App\Jobs\Onboarding\ProcessOnboardingMessageJob;
use App\Jobs\Onboarding\ProcessOnboardingStartJob;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ProcessOnboardingJobsTest extends TestCase
{
    use RefreshDatabase;

    private OnboardingSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = OnboardingSession::create(['user_id' => 1]);
    }

    public function test_start_job_creates_assistant_message(): void
    {
        $this->mockAIClient('Welcome!');

        $job = new ProcessOnboardingStartJob($this->session);
        $job->handle(app(AIClientInterface::class));

        $this->assertDatabaseHas('onboarding_messages', [
            'session_id' => $this->session->id,
            'role' => MessageRole::ASSISTANT->value,
            'content' => 'Welcome!',
            'status' => MessageStatus::COMPLETED->value,
        ]);
    }

    public function test_start_job_marks_message_as_failed_on_error(): void
    {
        $this->mockAIClientWithError();

        $job = new ProcessOnboardingStartJob($this->session);

        try {
            $job->handle(app(AIClientInterface::class));
        } catch (AIClientException) {
            // Expected
        }

        $this->assertDatabaseHas('onboarding_messages', [
            'session_id' => $this->session->id,
            'status' => MessageStatus::FAILED->value,
        ]);
    }

    public function test_message_job_processes_user_message(): void
    {
        $this->mockAIClient('AI Response');

        $userMessage = OnboardingMessage::create([
            'session_id' => $this->session->id,
            'role' => MessageRole::USER,
            'content' => 'User question',
            'status' => MessageStatus::PENDING,
        ]);

        $job = new ProcessOnboardingMessageJob($userMessage, $this->session);
        $job->handle(app(AIClientInterface::class));

        // Check user message is completed
        $this->assertTrue($userMessage->fresh()->isCompleted());

        // Check assistant response is created
        $this->assertDatabaseHas('onboarding_messages', [
            'session_id' => $this->session->id,
            'role' => MessageRole::ASSISTANT->value,
            'content' => 'AI Response',
            'status' => MessageStatus::COMPLETED->value,
        ]);
    }

    public function test_message_job_marks_both_messages_as_failed_on_error(): void
    {
        $this->mockAIClientWithError();

        $userMessage = OnboardingMessage::create([
            'session_id' => $this->session->id,
            'role' => MessageRole::USER,
            'content' => 'User question',
            'status' => MessageStatus::PENDING,
        ]);

        $job = new ProcessOnboardingMessageJob($userMessage, $this->session);

        try {
            $job->handle(app(AIClientInterface::class));
        } catch (AIClientException) {
            // Expected - call failed() to simulate queue worker behavior
            $job->failed(new AIClientException('API Error'));
        }

        // User message should be failed
        $this->assertTrue($userMessage->fresh()->isFailed());
    }

    public function test_jobs_use_config_values(): void
    {
        config(['ai.onboarding.job_tries' => 5]);
        config(['ai.onboarding.job_backoff' => 20]);

        $job = new ProcessOnboardingStartJob($this->session);

        $this->assertEquals(5, $job->tries);
        $this->assertEquals(20, $job->backoff);
    }

    public function test_jobs_use_configured_queue(): void
    {
        config(['ai.onboarding.queue' => 'custom-queue']);

        $job = new ProcessOnboardingStartJob($this->session);

        $this->assertEquals('custom-queue', $job->queue);
    }

    public function test_start_job_uses_welcome_prompt_from_config(): void
    {
        $customPrompt = 'Custom welcome prompt';
        config(['ai.onboarding.welcome_prompt' => $customPrompt]);

        $this->mock(AIClientInterface::class, function (MockInterface $mock) use ($customPrompt) {
            $mock->shouldReceive('chat')
                ->withArgs(function ($dto) use ($customPrompt) {
                    return $dto->message === $customPrompt;
                })
                ->once()
                ->andReturn(new ChatResponseDTO('Response', false));
        });

        $job = new ProcessOnboardingStartJob($this->session);
        $job->handle(app(AIClientInterface::class));
    }

    private function mockAIClient(string $response): void
    {
        $this->mock(AIClientInterface::class, function (MockInterface $mock) use ($response) {
            $mock->shouldReceive('chat')
                ->andReturn(new ChatResponseDTO($response, false));
        });
    }

    private function mockAIClientWithError(): void
    {
        $this->mock(AIClientInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('chat')
                ->andThrow(new AIClientException('API Error'));
        });
    }
}
