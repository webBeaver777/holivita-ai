<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\OnboardingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_empty_list(): void
    {
        $response = $this->getJson('/api/summaries');

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.items', []);
    }

    public function test_index_returns_completed_sessions_with_summary(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->markAsCompleted(['health' => 'good']);

        $response = $this->getJson('/api/summaries');

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.user_id', 123)
            ->assertJsonPath('data.items.0.summary.health', 'good');
    }

    public function test_index_excludes_incomplete_sessions(): void
    {
        OnboardingSession::create(['user_id' => 123]);

        $response = $this->getJson('/api/summaries');

        $response->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_index_filters_by_user_id(): void
    {
        $session1 = OnboardingSession::create(['user_id' => 123]);
        $session1->markAsCompleted(['data' => '1']);

        $session2 = OnboardingSession::create(['user_id' => 456]);
        $session2->markAsCompleted(['data' => '2']);

        $response = $this->getJson('/api/summaries?user_id=123');

        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.user_id', 123);
    }

    public function test_index_paginates_results(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $session = OnboardingSession::create(['user_id' => $i]);
            $session->markAsCompleted(['index' => $i]);
        }

        $response = $this->getJson('/api/summaries?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.pagination.per_page', 5)
            ->assertJsonPath('data.pagination.total', 20);
    }

    public function test_show_returns_session_summary(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);
        $session->markAsCompleted(['detailed' => 'summary']);

        $response = $this->getJson("/api/summaries/{$session->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $session->id,
                    'user_id' => 123,
                    'summary' => ['detailed' => 'summary'],
                ],
            ]);
    }

    public function test_show_returns_404_for_incomplete_session(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $response = $this->getJson("/api/summaries/{$session->id}");

        $response->assertNotFound()
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_show_returns_404_for_nonexistent_session(): void
    {
        $response = $this->getJson('/api/summaries/00000000-0000-0000-0000-000000000000');

        $response->assertNotFound();
    }
}
