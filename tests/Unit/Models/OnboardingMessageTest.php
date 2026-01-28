<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\OnboardingMessage;
use App\Models\OnboardingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingMessageTest extends TestCase
{
    use RefreshDatabase;

    private OnboardingSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = OnboardingSession::create(['user_id' => 1]);
    }

    public function test_can_create_message(): void
    {
        $message = OnboardingMessage::create([
            'session_id' => $this->session->id,
            'role' => MessageRole::USER,
            'content' => 'Test message',
        ]);

        $this->assertDatabaseHas('onboarding_messages', [
            'id' => $message->id,
            'content' => 'Test message',
        ]);
    }

    public function test_mark_as_processing(): void
    {
        $message = $this->createMessage();

        $message->markAsProcessing();

        $this->assertTrue($message->fresh()->isProcessing());
    }

    public function test_mark_as_completed_without_content(): void
    {
        $message = $this->createMessage();

        $message->markAsCompleted();

        $this->assertTrue($message->fresh()->isCompleted());
        $this->assertEquals('Test', $message->fresh()->content);
    }

    public function test_mark_as_completed_with_content(): void
    {
        $message = $this->createMessage();

        $message->markAsCompleted('New content');

        $message->refresh();
        $this->assertTrue($message->isCompleted());
        $this->assertEquals('New content', $message->content);
    }

    public function test_mark_as_failed(): void
    {
        $message = $this->createMessage();

        $message->markAsFailed('Error occurred');

        $message->refresh();
        $this->assertTrue($message->isFailed());
        $this->assertEquals('Error occurred', $message->error_message);
    }

    public function test_scope_pending(): void
    {
        $this->createMessage(MessageStatus::PENDING);
        $this->createMessage(MessageStatus::COMPLETED);

        $pending = OnboardingMessage::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertTrue($pending->first()->isPending());
    }

    public function test_scope_processing(): void
    {
        $this->createMessage(MessageStatus::PROCESSING);
        $this->createMessage(MessageStatus::COMPLETED);

        $processing = OnboardingMessage::processing()->get();

        $this->assertCount(1, $processing);
        $this->assertTrue($processing->first()->isProcessing());
    }

    public function test_scope_completed(): void
    {
        $this->createMessage(MessageStatus::PENDING);
        $this->createMessage(MessageStatus::COMPLETED);

        $completed = OnboardingMessage::completed()->get();

        $this->assertCount(1, $completed);
        $this->assertTrue($completed->first()->isCompleted());
    }

    public function test_scope_failed(): void
    {
        $this->createMessage(MessageStatus::FAILED);
        $this->createMessage(MessageStatus::COMPLETED);

        $failed = OnboardingMessage::failed()->get();

        $this->assertCount(1, $failed);
        $this->assertTrue($failed->first()->isFailed());
    }

    public function test_scope_in_progress(): void
    {
        $this->createMessage(MessageStatus::PENDING);
        $this->createMessage(MessageStatus::PROCESSING);
        $this->createMessage(MessageStatus::COMPLETED);

        $inProgress = OnboardingMessage::inProgress()->get();

        $this->assertCount(2, $inProgress);
    }

    public function test_scope_assistant(): void
    {
        $this->createMessage(MessageStatus::COMPLETED, MessageRole::ASSISTANT);
        $this->createMessage(MessageStatus::COMPLETED, MessageRole::USER);

        $assistant = OnboardingMessage::assistant()->get();

        $this->assertCount(1, $assistant);
        $this->assertEquals(MessageRole::ASSISTANT, $assistant->first()->role);
    }

    public function test_scope_user(): void
    {
        $this->createMessage(MessageStatus::COMPLETED, MessageRole::ASSISTANT);
        $this->createMessage(MessageStatus::COMPLETED, MessageRole::USER);

        $user = OnboardingMessage::user()->get();

        $this->assertCount(1, $user);
        $this->assertEquals(MessageRole::USER, $user->first()->role);
    }

    public function test_to_ai_format(): void
    {
        $message = $this->createMessage(MessageStatus::COMPLETED, MessageRole::USER);
        $message->content = 'Hello AI';

        $format = $message->toAIFormat();

        $this->assertEquals([
            'role' => 'user',
            'content' => 'Hello AI',
        ], $format);
    }

    public function test_belongs_to_session(): void
    {
        $message = $this->createMessage();

        $this->assertInstanceOf(OnboardingSession::class, $message->session);
        $this->assertEquals($this->session->id, $message->session->id);
    }

    private function createMessage(
        MessageStatus $status = MessageStatus::PENDING,
        MessageRole $role = MessageRole::ASSISTANT
    ): OnboardingMessage {
        return OnboardingMessage::create([
            'session_id' => $this->session->id,
            'role' => $role,
            'content' => 'Test',
            'status' => $status,
        ]);
    }
}
