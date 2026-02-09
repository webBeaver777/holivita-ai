<?php

declare(strict_types=1);

namespace App\Http\Requests\Voice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос статуса транскрипции.
 */
class TranscriptionStatusRequest extends FormRequest
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
        return [
            'transcription_id' => ['required', 'uuid'],
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transcription_id.required' => 'ID транскрипции обязателен.',
            'transcription_id.uuid' => 'Некорректный формат ID транскрипции.',
            'user_id.required' => 'User ID обязателен.',
        ];
    }

    public function getTranscriptionId(): string
    {
        return $this->validated('transcription_id');
    }

    public function getUserId(): int
    {
        return (int) $this->validated('user_id');
    }
}
