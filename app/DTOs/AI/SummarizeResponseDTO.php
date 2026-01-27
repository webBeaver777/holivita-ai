<?php

declare(strict_types=1);

namespace App\DTOs\AI;

/**
 * DTO для ответа суммаризации.
 */
readonly class SummarizeResponseDTO
{
    /**
     * @param  array<string, mixed>  $summary  Структурированная суммаризация
     * @param  string  $rawResponse  Сырой ответ от AI
     */
    public function __construct(
        public array $summary,
        public string $rawResponse = '',
    ) {}

    /**
     * Преобразовать в массив.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->summary;
    }
}
