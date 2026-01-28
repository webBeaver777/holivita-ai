<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\AI\AIClientInterface;
use App\DTOs\AI\ChatResponseDTO;
use App\DTOs\AI\SummarizeResponseDTO;
use App\Models\OnboardingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OnboardingChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(AIClientInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('chat')
                ->andReturn(new ChatResponseDTO(
                    message: 'Привет! Я ваш ассистент.',
                    isComplete: false,
                ));

            $mock->shouldReceive('summarize')
                ->andReturn(new SummarizeResponseDTO(
                    summary: ['health_concerns' => ['stress']],
                ));
        });
    }

    public function test_validate_user_success(): void
    {
        $response = $this->postJson('/api/onboarding/validate-user', [
            'user_id' => 123,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'user_id' => 123,
            ]);
    }

    public function test_validate_user_fails_without_user_id(): void
    {
        $response = $this->postJson('/api/onboarding/validate-user', []);

        $response->assertUnprocessable();
    }

    public function test_validate_user_fails_with_active_session(): void
    {
        OnboardingSession::create(['user_id' => 123]);

        $response = $this->postJson('/api/onboarding/validate-user', [
            'user_id' => 123,
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_chat_starts_onboarding_with_empty_message(): void
    {
        $response = $this->postJson('/api/onboarding/chat', [
            'user_id' => 123,
            'message' => '',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['message', 'completed', 'session_id'],
            ]);

        $this->assertDatabaseHas('onboarding_sessions', [
            'user_id' => 123,
        ]);
    }

    public function test_chat_processes_user_message(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $response = $this->postJson('/api/onboarding/chat', [
            'user_id' => 123,
            'message' => 'Привет, у меня болит голова',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                ],
            ]);
    }

    public function test_chat_uses_existing_session(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $this->postJson('/api/onboarding/chat', [
            'user_id' => 123,
            'message' => 'Test',
        ]);

        $this->assertDatabaseCount('onboarding_sessions', 1);
    }

    public function test_complete_returns_summary(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->messages()->create([
            'role' => 'user',
            'content' => 'Test message',
        ]);

        $response = $this->postJson('/api/onboarding/complete', [
            'user_id' => 123,
            'session_id' => $session->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => ['summary', 'session_id'],
            ]);
    }

    public function test_complete_fails_for_nonexistent_session(): void
    {
        $response = $this->postJson('/api/onboarding/complete', [
            'user_id' => 123,
            'session_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        // Validation returns 422 because session_id has 'exists' rule
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['session_id']);
    }

    public function test_complete_returns_cached_summary_for_completed_session(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->markAsCompleted(['cached' => 'summary']);

        $response = $this->postJson('/api/onboarding/complete', [
            'user_id' => 123,
            'session_id' => $session->id,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => ['cached' => 'summary'],
                ],
            ]);
    }

    public function test_history_returns_empty_for_new_user(): void
    {
        $response = $this->getJson('/api/onboarding/history?user_id=123');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'messages' => [],
                    'session_id' => null,
                    'is_completed' => false,
                ],
            ]);
    }

    public function test_history_returns_messages_for_existing_session(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->messages()->create([
            'role' => 'assistant',
            'content' => 'Hello!',
        ]);

        $response = $this->getJson('/api/onboarding/history?user_id=123');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $session->id,
                    'is_completed' => false,
                ],
            ]);
        $this->assertCount(1, $response->json('data.messages'));
    }

    public function test_user_can_start_new_session_after_completing_previous(): void
    {
        $oldSession = OnboardingSession::create(['user_id' => 123]);
        $oldSession->markAsCompleted(['test' => 'data']);

        $response = $this->postJson('/api/onboarding/validate-user', [
            'user_id' => 123,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }
}
