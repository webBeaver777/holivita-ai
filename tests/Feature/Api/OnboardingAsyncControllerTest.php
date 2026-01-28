<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\Onboarding\ProcessOnboardingMessageJob;
use App\Jobs\Onboarding\ProcessOnboardingStartJob;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OnboardingAsyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_async_chat_dispatches_start_job_for_empty_message(): void
    {
        $response = $this->postJson('/api/onboarding/async/chat', [
            'user_id' => 123,
            'message' => '',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['session_id'],
            ]);

        Queue::assertPushed(ProcessOnboardingStartJob::class);
    }

    public function test_async_chat_dispatches_message_job(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $response = $this->postJson('/api/onboarding/async/chat', [
            'user_id' => 123,
            'message' => 'У меня болит голова',
        ]);

        $response->assertStatus(202)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                    'status' => 'pending',
                ],
            ]);

        Queue::assertPushed(ProcessOnboardingMessageJob::class);
    }

    public function test_async_chat_creates_user_message(): void
    {
        OnboardingSession::create(['user_id' => 123]);

        $this->postJson('/api/onboarding/async/chat', [
            'user_id' => 123,
            'message' => 'Test message',
        ]);

        $this->assertDatabaseHas('onboarding_messages', [
            'content' => 'Test message',
            'role' => MessageRole::USER->value,
            'status' => MessageStatus::PENDING->value,
        ]);
    }

    public function test_async_chat_rejects_when_message_in_progress(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT,
            'content' => '',
            'status' => MessageStatus::PROCESSING,
        ]);

        $response = $this->postJson('/api/onboarding/async/chat', [
            'user_id' => 123,
            'message' => 'Another message',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);

        Queue::assertNotPushed(ProcessOnboardingMessageJob::class);
    }

    public function test_status_returns_pending_when_no_response(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $response = $this->getJson("/api/onboarding/async/status?user_id=123&session_id={$session->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'pending',
                    'message' => null,
                    'completed' => false,
                ],
            ]);
    }

    public function test_status_returns_processing_status(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT,
            'content' => '',
            'status' => MessageStatus::PROCESSING,
        ]);

        $response = $this->getJson("/api/onboarding/async/status?user_id=123&session_id={$session->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'processing',
                    'message' => null,
                    'completed' => false,
                ],
            ]);
    }

    public function test_status_returns_completed_message(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT,
            'content' => 'AI Response',
            'status' => MessageStatus::COMPLETED,
        ]);

        $response = $this->getJson("/api/onboarding/async/status?user_id=123&session_id={$session->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'message' => 'AI Response',
                    'completed' => true,
                ],
            ]);
    }

    public function test_status_returns_error_for_failed_message(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        OnboardingMessage::create([
            'session_id' => $session->id,
            'role' => MessageRole::ASSISTANT,
            'content' => '',
            'status' => MessageStatus::FAILED,
            'error_message' => 'AI service unavailable',
        ]);

        $response = $this->getJson("/api/onboarding/async/status?user_id=123&session_id={$session->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'failed',
                    'error' => 'AI service unavailable',
                    'completed' => false,
                ],
            ]);
    }

    public function test_status_returns_404_for_nonexistent_session(): void
    {
        $response = $this->getJson('/api/onboarding/async/status?user_id=123&session_id=00000000-0000-0000-0000-000000000000');

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_status_validates_required_fields(): void
    {
        $response = $this->getJson('/api/onboarding/async/status');

        $response->assertUnprocessable();
    }
}
