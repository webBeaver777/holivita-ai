<?php

declare(strict_types=1);

namespace App\DTOs\AI;

/**
 * DTO для запроса к AI чату.
 */
readonly class ChatRequestDTO
{
    /**
     * @param  string  $message  Сообщение пользователя
     * @param  array<array{role: string, content: string}>  $conversationHistory  История диалога
     * @param  string|null  $sessionId  ID сессии для контекста
     */
    public function __construct(
        public string $message,
        public array $conversationHistory = [],
        public ?string $sessionId = null,
    ) {}

    /**
     * Создать DTO из массива.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'],
            conversationHistory: $data['conversation_history'] ?? [],
            sessionId: $data['session_id'] ?? null,
        );
    }
}
