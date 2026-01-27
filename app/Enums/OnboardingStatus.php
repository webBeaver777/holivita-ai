<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статусы сессии онбординга.
 */
enum OnboardingStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';

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
