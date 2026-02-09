<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\VoiceTranscriptionException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VoiceTranscriptionExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_provider_unavailable_exception(): void
    {
        $exception = VoiceTranscriptionException::providerUnavailable('anythingllm');

        $this->assertEquals('Провайдер anythingllm недоступен', $exception->getMessage());
        $this->assertEquals('anythingllm', $exception->getProvider());
    }

    #[Test]
    public function it_creates_unsupported_format_exception(): void
    {
        $exception = VoiceTranscriptionException::unsupportedFormat('txt', 'openai');

        $this->assertEquals('Формат txt не поддерживается провайдером openai', $exception->getMessage());
        $this->assertEquals('openai', $exception->getProvider());
    }

    #[Test]
    public function it_creates_file_too_large_exception(): void
    {
        $exception = VoiceTranscriptionException::fileTooLarge(50 * 1024 * 1024, 25 * 1024 * 1024, 'openai');

        $this->assertStringContainsString('Файл слишком большой', $exception->getMessage());
        $this->assertEquals('openai', $exception->getProvider());
    }

    #[Test]
    public function it_creates_transcription_failed_exception(): void
    {
        $exception = VoiceTranscriptionException::transcriptionFailed('API error', 'anythingllm');

        $this->assertEquals('Ошибка транскрипции: API error', $exception->getMessage());
        $this->assertEquals('anythingllm', $exception->getProvider());
    }

    #[Test]
    public function it_returns_null_provider_when_not_set(): void
    {
        $exception = new VoiceTranscriptionException('General error');

        $this->assertNull($exception->getProvider());
    }
}
