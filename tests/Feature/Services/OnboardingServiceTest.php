<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatResponseDTO;
use App\DTOs\AI\SummarizeResponseDTO;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\Onboarding\ProcessOnboardingMessageJob;
use App\Jobs\Onboarding\ProcessOnboardingStartJob;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery\MockInterface;
use Tests\TestCase;

class OnboardingServiceTest extends TestCase
{
    use RefreshDatabase;

    private OnboardingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AIClientInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('chat')
                ->andReturn(new ChatResponseDTO('Response', false));
            $mock->shouldReceive('summarize')
                ->andReturn(new SummarizeResponseDTO(['key' => 'value']));
        });

        $this->service = app(OnboardingService::class);
    }

    public function test_can_start_onboarding_returns_true_for_new_user(): void
    {
        $result = $this->service->canStartOnboarding(123);

        $this->assertTrue($result['can_start']);
        $this->assertNull($result['reason']);
    }

    public function test_can_start_onboarding_returns_false_for_active_session(): void
    {
        OnboardingSession::create(['user_id' => 123]);

        $result = $this->service->canStartOnboarding(123);

        $this->assertFalse($result['can_start']);
        $this->assertNotNull($result['reason']);
    }

    public function test_can_start_onboarding_allows_new_session_after_completed(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->markAsCompleted(['test' => 'data']);

        $result = $this->service->canStartOnboarding(123);

        $this->assertTrue($result['can_start']);
    }

    public function test_get_or_create_session_creates_new_session(): void
    {
        $session = $this->service->getOrCreateSession(123);

        $this->assertInstanceOf(OnboardingSession::class, $session);
        $this->assertEquals(123, $session->user_id);
        $this->assertDatabaseCount('onboarding_sessions', 1);
    }

    public function test_get_or_create_session_returns_existing_session(): void
    {
        $existing = OnboardingSession::create(['user_id' => 123]);

        $session = $this->service->getOrCreateSession(123);

        $this->assertEquals($existing->id, $session->id);
        $this->assertDatabaseCount('onboarding_sessions', 1);
    }

    public function test_start_onboarding_creates_assistant_message(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $response = $this->service->startOnboarding($session);

        $this->assertInstanceOf(ChatResponseDTO::class, $response);
        $this->assertDatabaseHas('onboarding_messages', [
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT->value,
        ]);
    }

    public function test_process_message_saves_user_and_assistant_messages(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $this->service->processMessage($session, 'User message');

        $this->assertDatabaseHas('onboarding_messages', [
            'session_id' => $session->id,
            'role' => MessageRole::USER->value,
            'content' => 'User message',
        ]);
        $this->assertDatabaseHas('onboarding_messages', [
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT->value,
        ]);
    }

    public function test_complete_onboarding_marks_session_completed(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $this->service->completeOnboarding($session);

        $this->assertTrue($session->fresh()->isCompleted());
    }

    public function test_complete_onboarding_returns_cached_summary(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->markAsCompleted(['cached' => 'summary']);

        $response = $this->service->completeOnboarding($session);

        $this->assertEquals(['cached' => 'summary'], $response->summary);
    }

    public function test_get_conversation_history(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->messages()->create([
            'role' => MessageRole::USER,
            'content' => 'Hello',
        ]);
        $session->messages()->create([
            'role' => MessageRole::ASSISTANT,
            'content' => 'Hi there',
        ]);

        $history = $this->service->getConversationHistory($session);

        $this->assertCount(2, $history);
        $this->assertEquals('user', $history[0]['role']);
        $this->assertEquals('assistant', $history[1]['role']);
    }

    public function test_find_session(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $found = $this->service->findSession($session->id, 123);

        $this->assertEquals($session->id, $found->id);
    }

    public function test_find_session_returns_null_for_wrong_user(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $found = $this->service->findSession($session->id, 456);

        $this->assertNull($found);
    }

    public function test_start_onboarding_async_dispatches_job(): void
    {
        Queue::fake();
        $session = OnboardingSession::create(['user_id' => 123]);

        $this->service->startOnboardingAsync($session);

        Queue::assertPushed(ProcessOnboardingStartJob::class, function ($job) use ($session) {
            return $job->session->id === $session->id;
        });
    }

    public function test_process_message_async_creates_pending_message(): void
    {
        Queue::fake();
        $session = OnboardingSession::create(['user_id' => 123]);

        $message = $this->service->processMessageAsync($session, 'Test');

        $this->assertEquals(MessageStatus::PENDING, $message->status);
        $this->assertEquals('Test', $message->content);
        Queue::assertPushed(ProcessOnboardingMessageJob::class);
    }

    public function test_get_message_status_returns_pending_for_no_messages(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $status = $this->service->getMessageStatus($session);

        $this->assertEquals('pending', $status['status']);
        $this->assertNull($status['message']);
    }

    public function test_get_message_status_returns_completed_message(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT,
            'content' => 'AI says hello',
            'status' => MessageStatus::COMPLETED,
        ]);

        $status = $this->service->getMessageStatus($session);

        $this->assertEquals('completed', $status['status']);
        $this->assertEquals('AI says hello', $status['message']);
    }

    public function test_has_processing_messages(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $this->assertFalse($this->service->hasProcessingMessages($session));

        OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT,
            'content' => '',
            'status' => MessageStatus::PROCESSING,
        ]);

        $this->assertTrue($this->service->hasProcessingMessages($session));
    }
}
