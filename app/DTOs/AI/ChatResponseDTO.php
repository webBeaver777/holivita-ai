<?php

declare(strict_types=1);

namespace App\DTOs\AI;

/**
 * DTO для ответа от AI чата.
 */
readonly class ChatResponseDTO
{
    /**
     * @param  string  $message  Ответ ассистента
     * @param  bool  $isComplete  Флаг завершения онбординга
     */
    public function __construct(
        public string $message,
        public bool $isComplete = false,
    ) {}

    /**
     * Преобразовать в массив.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'completed' => $this->isComplete,
        ];
    }
}
