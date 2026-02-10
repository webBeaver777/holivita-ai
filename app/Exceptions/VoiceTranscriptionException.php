<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Исключение для ошибок транскрипции голоса.
 */
final class VoiceTranscriptionException extends Exception
{
    public static function unsupportedFormat(string $format): self
    {
        return new self("Формат {$format} не поддерживается.");
    }

    public static function fileTooLarge(int $size, int $maxSize): self
    {
        $sizeMb = round($size / 1024 / 1024, 2);
        $maxSizeMb = round($maxSize / 1024 / 1024, 2);

        return new self("Файл слишком большой ({$sizeMb}MB). Максимум: {$maxSizeMb}MB.");
    }

    public static function transcriptionFailed(string $reason): self
    {
        return new self("Ошибка транскрипции: {$reason}");
    }
}
