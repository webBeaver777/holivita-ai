<?php

declare(strict_types=1);

namespace App\DTOs\AI;

/**
 * DTO для запроса к чату AI.
 */
final readonly class ChatRequestDTO
{
    public function __construct(
        public string $message,
        public ?string $sessionId = null,
    ) {}
}
