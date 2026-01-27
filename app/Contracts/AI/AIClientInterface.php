<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\DTOs\AI\ChatRequestDTO;
use App\DTOs\AI\ChatResponseDTO;
use App\DTOs\AI\SummarizeRequestDTO;
use App\DTOs\AI\SummarizeResponseDTO;

/**
 * Контракт для AI клиентов.
 * Позволяет легко заменить AnythingLLM на другой провайдер (OpenAI, Claude и т.д.)
 */
interface AIClientInterface
{
    /**
     * Отправить сообщение в чат и получить ответ.
     */
    public function chat(ChatRequestDTO $request): ChatResponseDTO;

    /**
     * Суммаризировать диалог.
     */
    public function summarize(SummarizeRequestDTO $request): SummarizeResponseDTO;
}
