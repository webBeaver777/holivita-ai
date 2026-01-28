<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\MessageStatus;
use PHPUnit\Framework\TestCase;

class MessageStatusTest extends TestCase
{
    public function test_has_correct_values(): void
    {
        $this->assertEquals('pending', MessageStatus::PENDING->value);
        $this->assertEquals('processing', MessageStatus::PROCESSING->value);
        $this->assertEquals('completed', MessageStatus::COMPLETED->value);
        $this->assertEquals('failed', MessageStatus::FAILED->value);
    }

    public function test_values_returns_all_values(): void
    {
        $values = MessageStatus::values();

        $this->assertCount(4, $values);
        $this->assertContains('pending', $values);
        $this->assertContains('processing', $values);
        $this->assertContains('completed', $values);
        $this->assertContains('failed', $values);
    }
}
