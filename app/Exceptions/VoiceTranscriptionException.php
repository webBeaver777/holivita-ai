<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Исключение для ошибок транскрипции голоса.
 */
class VoiceTranscriptionException extends Exception
{
    public function __construct(
        string $message = 'Ошибка транскрипции голоса',
        public readonly ?string $provider = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Получить провайдера, вызвавшего ошибку.
     */
    public function getProvider(): ?string
    {
        return $this->provider;
    }

    /**
     * Создать исключение для недоступного провайдера.
     */
    public static function providerUnavailable(string $provider): self
    {
        return new self(
            message: "Провайдер {$provider} недоступен",
            provider: $provider,
        );
    }

    /**
     * Создать исключение для неподдерживаемого формата.
     */
    public static function unsupportedFormat(string $format, string $provider): self
    {
        return new self(
            message: "Формат {$format} не поддерживается провайдером {$provider}",
            provider: $provider,
        );
    }

    /**
     * Создать исключение для превышения размера файла.
     */
    public static function fileTooLarge(int $size, int $maxSize, string $provider): self
    {
        $sizeMb = round($size / 1024 / 1024, 2);
        $maxSizeMb = round($maxSize / 1024 / 1024, 2);

        return new self(
            message: "Файл слишком большой ({$sizeMb}MB). Максимум: {$maxSizeMb}MB для {$provider}",
            provider: $provider,
        );
    }

    /**
     * Создать исключение для ошибки транскрипции.
     */
    public static function transcriptionFailed(string $reason, string $provider): self
    {
        return new self(
            message: "Ошибка транскрипции: {$reason}",
            provider: $provider,
        );
    }
}
