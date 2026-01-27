<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос валидации пользователя перед онбордингом.
 */
class ValidateUserRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ID пользователя обязателен.',
            'user_id.integer' => 'ID пользователя должен быть числом.',
            'user_id.min' => 'ID пользователя должен быть положительным числом.',
        ];
    }

    public function getUserId(): int
    {
        return (int) $this->validated('user_id');
    }
}
