<?php

declare(strict_types=1);

namespace Tests\Unit\DTOs\Voice;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TranscriptionDTOsTest extends TestCase
{
    #[Test]
    public function transcription_request_dto_holds_correct_data(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');
        $sessionId = '550e8400-e29b-41d4-a716-446655440000';

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'ru',
            sessionId: $sessionId,
        );

        $this->assertSame($audioFile, $dto->audio);
        $this->assertEquals('ru', $dto->language);
        $this->assertEquals($sessionId, $dto->sessionId);
    }

    #[Test]
    public function transcription_request_dto_returns_file_path(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'en',
        );

        $this->assertNotEmpty($dto->getFilePath());
    }

    #[Test]
    public function transcription_request_dto_returns_file_path_from_string(): void
    {
        $dto = new TranscriptionRequestDTO(
            audio: '/tmp/test-audio.webm',
            language: 'ru',
        );

        $this->assertEquals('/tmp/test-audio.webm', $dto->getFilePath());
    }

    #[Test]
    public function transcription_request_dto_returns_mime_type(): void
    {
        $audioFile = UploadedFile::fake()->create('audio.webm', 1024, 'audio/webm');

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'en',
        );

        $this->assertNotNull($dto->getMimeType());
    }

    #[Test]
    public function transcription_request_dto_uses_explicit_mime_type(): void
    {
        $dto = new TranscriptionRequestDTO(
            audio: '/tmp/test.webm',
            language: 'ru',
            mimeType: 'audio/ogg',
        );

        $this->assertEquals('audio/ogg', $dto->getMimeType());
    }

    #[Test]
    public function transcription_request_dto_returns_file_name(): void
    {
        $audioFile = UploadedFile::fake()->create('test_audio.webm', 1024, 'audio/webm');

        $dto = new TranscriptionRequestDTO(
            audio: $audioFile,
            language: 'en',
        );

        $this->assertStringContainsString('test_audio', $dto->getFileName());
    }

    #[Test]
    public function transcription_response_dto_holds_correct_data(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: 'Привет, мир!',
            language: 'ru',
            confidence: 0.95,
            duration: 2.5,
            provider: 'openai',
        );

        $this->assertEquals('Привет, мир!', $dto->text);
        $this->assertEquals('ru', $dto->language);
        $this->assertEquals(0.95, $dto->confidence);
        $this->assertEquals(2.5, $dto->duration);
        $this->assertEquals('openai', $dto->provider);
    }

    #[Test]
    public function transcription_response_dto_is_empty_returns_true_for_empty_text(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: '',
            language: 'ru',
            provider: 'openai',
        );

        $this->assertTrue($dto->isEmpty());
    }

    #[Test]
    public function transcription_response_dto_is_empty_returns_true_for_whitespace_text(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: '   ',
            language: 'ru',
            provider: 'openai',
        );

        $this->assertTrue($dto->isEmpty());
    }

    #[Test]
    public function transcription_response_dto_is_empty_returns_false_for_valid_text(): void
    {
        $dto = new TranscriptionResponseDTO(
            text: 'Hello',
            language: 'en',
            provider: 'openai',
        );

        $this->assertFalse($dto->isEmpty());
    }
}
