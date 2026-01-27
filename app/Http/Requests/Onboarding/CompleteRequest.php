<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос завершения онбординга.
 */
class CompleteRequest extends FormRequest
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
            'session_id' => ['required', 'uuid', 'exists:onboarding_sessions,id'],
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
            'session_id.required' => 'ID сессии обязателен.',
            'session_id.exists' => 'Сессия не найдена.',
        ];
    }

    public function getUserId(): int
    {
        return (int) $this->validated('user_id');
    }

    public function getSessionId(): string
    {
        return $this->validated('session_id');
    }
}
