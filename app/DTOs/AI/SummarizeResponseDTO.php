<?php

declare(strict_types=1);

namespace App\DTOs\AI;

/**
 * DTO для ответа суммаризации.
 */
final readonly class SummarizeResponseDTO
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(
        public array $summary,
    ) {}
}
