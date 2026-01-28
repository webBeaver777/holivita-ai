<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\OnboardingStatus;
use App\Models\OnboardingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_session(): void
    {
        $session = OnboardingSession::create(['user_id' => 123]);

        $this->assertDatabaseHas('onboarding_sessions', [
            'user_id' => 123,
            'status' => OnboardingStatus::IN_PROGRESS->value,
        ]);
        $this->assertNotNull($session->id);
    }

    public function test_session_has_uuid_primary_key(): void
    {
        $session = OnboardingSession::create(['user_id' => 1]);

        $this->assertIsString($session->id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $session->id
        );
    }

    public function test_scope_for_user(): void
    {
        OnboardingSession::create(['user_id' => 1]);
        OnboardingSession::create(['user_id' => 2]);
        OnboardingSession::create(['user_id' => 1]);

        $sessions = OnboardingSession::forUser(1)->get();

        $this->assertCount(2, $sessions);
    }

    public function test_scope_in_progress(): void
    {
        $inProgress = OnboardingSession::create(['user_id' => 1]);
        $completed = OnboardingSession::create(['user_id' => 2]);
        $completed->update(['status' => OnboardingStatus::COMPLETED]);

        $sessions = OnboardingSession::inProgress()->get();

        $this->assertCount(1, $sessions);
        $this->assertEquals($inProgress->id, $sessions->first()->id);
    }

    public function test_scope_completed(): void
    {
        OnboardingSession::create(['user_id' => 1]);
        $completed = OnboardingSession::create(['user_id' => 2]);
        $completed->update([
            'status' => OnboardingStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        $sessions = OnboardingSession::completed()->get();

        $this->assertCount(1, $sessions);
        $this->assertEquals($completed->id, $sessions->first()->id);
    }

    public function test_is_completed(): void
    {
        $session = OnboardingSession::create(['user_id' => 1]);

        $this->assertFalse($session->isCompleted());

        $session->update(['status' => OnboardingStatus::COMPLETED]);

        $this->assertTrue($session->fresh()->isCompleted());
    }

    public function test_mark_as_completed(): void
    {
        $session = OnboardingSession::create(['user_id' => 1]);
        $summary = ['key' => 'value'];

        $session->markAsCompleted($summary);

        $session->refresh();
        $this->assertTrue($session->isCompleted());
        $this->assertEquals($summary, $session->summary_json);
        $this->assertNotNull($session->completed_at);
    }

    public function test_has_many_messages(): void
    {
        $session = OnboardingSession::create(['user_id' => 1]);
        $session->messages()->create([
            'role' => 'user',
            'content' => 'Test message',
        ]);

        $this->assertCount(1, $session->messages);
    }

    public function test_is_cancelled(): void
    {
        $session = OnboardingSession::create(['user_id' => 1]);

        $this->assertFalse($session->isCancelled());

        $session->markAsCancelled();

        $this->assertTrue($session->fresh()->isCancelled());
    }

    public function test_is_expired(): void
    {
        $session = OnboardingSession::create(['user_id' => 1]);

        $this->assertFalse($session->isExpired());

        $session->markAsExpired();

        $this->assertTrue($session->fresh()->isExpired());
    }

    public function test_is_active(): void
    {
        $session = OnboardingSession::create(['user_id' => 1]);

        $this->assertTrue($session->isActive());

        $session->markAsCompleted(['test' => 'data']);

        $this->assertFalse($session->fresh()->isActive());
    }

    public function test_scope_cancelled(): void
    {
        OnboardingSession::create(['user_id' => 1]);
        $cancelled = OnboardingSession::create(['user_id' => 2]);
        $cancelled->markAsCancelled();

        $sessions = OnboardingSession::cancelled()->get();

        $this->assertCount(1, $sessions);
        $this->assertEquals($cancelled->id, $sessions->first()->id);
    }

    public function test_scope_expired(): void
    {
        OnboardingSession::create(['user_id' => 1]);
        $expired = OnboardingSession::create(['user_id' => 2]);
        $expired->markAsExpired();

        $sessions = OnboardingSession::expired()->get();

        $this->assertCount(1, $sessions);
        $this->assertEquals($expired->id, $sessions->first()->id);
    }

    public function test_scope_stale(): void
    {
        OnboardingSession::create(['user_id' => 1]);

        $stale = OnboardingSession::create(['user_id' => 2]);
        OnboardingSession::where('id', $stale->id)->update(['updated_at' => now()->subHours(25)]);

        $staleSessions = OnboardingSession::stale(24)->get();

        $this->assertCount(1, $staleSessions);
        $this->assertEquals($stale->id, $staleSessions->first()->id);
    }
}
