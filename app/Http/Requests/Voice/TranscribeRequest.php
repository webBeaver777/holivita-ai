<?php

declare(strict_types=1);

namespace App\Http\Requests\Voice;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Запрос транскрипции аудио.
 */
class TranscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        $maxSize = (int) config('voice.max_file_size', 25 * 1024 * 1024);
        $maxSizeKb = (int) ($maxSize / 1024);

        return [
            'audio' => [
                'required',
                'file',
                'mimes:webm,wav,mp3,mp4,ogg,flac,mpeg,mpga',
                "max:{$maxSizeKb}",
            ],
            'language' => ['nullable', 'string', 'size:2'],
            'user_id' => ['required', 'integer', 'min:1'],
            'session_id' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'audio.required' => 'Аудио файл обязателен.',
            'audio.file' => 'Необходимо загрузить файл.',
            'audio.mimes' => 'Поддерживаемые форматы: webm, wav, mp3, mp4, ogg, flac.',
            'audio.max' => 'Максимальный размер файла: :max KB.',
            'language.size' => 'Код языка должен содержать 2 символа (например: ru, en).',
            'user_id.required' => 'User ID обязателен.',
        ];
    }

    public function getAudioFile(): UploadedFile
    {
        return $this->file('audio');
    }

    public function getLanguage(): string
    {
        return $this->validated('language') ?? config('voice.default_language', 'ru');
    }

    public function getUserId(): int
    {
        return (int) $this->validated('user_id');
    }

    public function getSessionId(): ?string
    {
        return $this->validated('session_id');
    }
}
