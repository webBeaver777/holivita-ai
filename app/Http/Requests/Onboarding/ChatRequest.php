<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос для отправки сообщения в чат онбординга.
 */
class ChatRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'min:1'],
            'session_id' => ['nullable', 'uuid'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ID пользователя обязателен.',
            'user_id.min' => 'ID пользователя должен быть положительным числом.',
            'message.max' => 'Сообщение слишком длинное (максимум :max символов).',
        ];
    }

    public function getUserId(): int
    {
        return (int) $this->validated('user_id');
    }

    public function getMessage(): ?string
    {
        return $this->validated('message');
    }

    public function getSessionId(): ?string
    {
        return $this->validated('session_id');
    }
}
