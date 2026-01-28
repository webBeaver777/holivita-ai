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
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';

    /**
     * Получить все значения enum.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isActive(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::EXPIRED], true);
    }
}
