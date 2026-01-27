<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Роли участников диалога.
 */
enum MessageRole: string
{
    case USER = 'user';
    case ASSISTANT = 'assistant';

    /**
     * Получить все значения enum.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
