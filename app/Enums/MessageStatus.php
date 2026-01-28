<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статус обработки сообщения.
 */
enum MessageStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
