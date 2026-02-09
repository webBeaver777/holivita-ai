<?php

declare(strict_types=1);

namespace App\DTOs\Voice;

/**
 * DTO для ответа транскрипции аудио.
 */
final readonly class TranscriptionResponseDTO
{
    public function __construct(
        public string $text,
        public ?string $language = null,
        public ?float $confidence = null,
        public ?float $duration = null,
        public ?string $provider = null,
    ) {}

    /**
     * Проверить, пустой ли результат.
     */
    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }
}
