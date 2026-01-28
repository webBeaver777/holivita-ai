<?php

declare(strict_types=1);

namespace App\DTOs\AI;

/**
 * DTO для запроса суммаризации.
 */
final readonly class SummarizeRequestDTO
{
    /**
     * @param  array<array{role: string, content: string}>  $messages  Сообщения для суммаризации
     * @param  string|null  $sessionId  ID сессии
     */
    public function __construct(
        public array $messages,
        public ?string $sessionId = null,
    ) {}
}
