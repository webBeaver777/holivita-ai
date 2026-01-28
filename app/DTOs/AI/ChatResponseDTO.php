<?php

declare(strict_types=1);

namespace App\DTOs\AI;

/**
 * DTO для ответа чата AI.
 */
final readonly class ChatResponseDTO
{
    public function __construct(
        public string $message,
        public bool $isComplete = false,
    ) {}
}
