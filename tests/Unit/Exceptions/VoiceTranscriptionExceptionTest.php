<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\VoiceTranscriptionException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoiceTranscriptionExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_unsupported_format_exception(): void
    {
        $exception = VoiceTranscriptionException::unsupportedFormat('txt');

        $this->assertEquals('Формат txt не поддерживается.', $exception->getMessage());
    }

    #[Test]
    public function it_creates_file_too_large_exception(): void
    {
        $exception = VoiceTranscriptionException::fileTooLarge(50 * 1024 * 1024, 25 * 1024 * 1024);

        $this->assertStringContainsString('Файл слишком большой', $exception->getMessage());
        $this->assertStringContainsString('50', $exception->getMessage());
        $this->assertStringContainsString('25', $exception->getMessage());
    }

    #[Test]
    public function it_creates_transcription_failed_exception(): void
    {
        $exception = VoiceTranscriptionException::transcriptionFailed('API error');

        $this->assertEquals('Ошибка транскрипции: API error', $exception->getMessage());
    }

    #[Test]
    public function it_creates_exception_with_custom_message(): void
    {
        $exception = new VoiceTranscriptionException('Custom error');

        $this->assertEquals('Custom error', $exception->getMessage());
    }
}
