<?php

declare(strict_types=1);

namespace App\Contracts\Voice;

use App\DTOs\Voice\TranscriptionRequestDTO;
use App\DTOs\Voice\TranscriptionResponseDTO;

/**
 * Контракт для сервисов транскрипции голоса.
 * Позволяет переключаться между провайдерами (AnythingLLM, OpenAI Whisper и т.д.)
 */
interface VoiceTranscriptionInterface
{
    /**
     * Транскрибировать аудио в текст.
     *
     * @throws \App\Exceptions\VoiceTranscriptionException
     */
    public function transcribe(TranscriptionRequestDTO $request): TranscriptionResponseDTO;

    /**
     * Проверить доступность сервиса.
     */
    public function isAvailable(): bool;

    /**
     * Получить название провайдера.
     */
    public function getProviderName(): string;

    /**
     * Получить поддерживаемые форматы аудио.
     *
     * @return array<string>
     */
    public function getSupportedFormats(): array;

    /**
     * Получить максимальный размер файла в байтах.
     */
    public function getMaxFileSize(): int;
}
