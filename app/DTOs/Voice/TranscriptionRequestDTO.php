<?php

declare(strict_types=1);

namespace App\DTOs\Voice;

use Illuminate\Http\UploadedFile;

/**
 * DTO для запроса транскрипции аудио.
 */
final readonly class TranscriptionRequestDTO
{
    public function __construct(
        public UploadedFile|string $audio,
        public ?string $language = 'ru',
        public ?string $sessionId = null,
        public ?string $mimeType = null,
    ) {}

    /**
     * Получить путь к файлу.
     */
    public function getFilePath(): string
    {
        if ($this->audio instanceof UploadedFile) {
            return $this->audio->getRealPath() ?: '';
        }

        return $this->audio;
    }

    /**
     * Получить MIME-тип файла.
     */
    public function getMimeType(): string
    {
        if ($this->mimeType !== null) {
            return $this->mimeType;
        }

        if ($this->audio instanceof UploadedFile) {
            return $this->audio->getMimeType() ?? 'audio/webm';
        }

        if (is_string($this->audio) && file_exists($this->audio)) {
            return mime_content_type($this->audio) ?: 'audio/webm';
        }

        return 'audio/webm';
    }

    /**
     * Получить оригинальное имя файла.
     */
    public function getFileName(): string
    {
        if ($this->audio instanceof UploadedFile) {
            return $this->audio->getClientOriginalName();
        }

        return basename($this->audio);
    }
}
