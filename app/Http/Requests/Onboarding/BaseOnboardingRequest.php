<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Базовый класс для запросов онбординга.
 * Содержит общие методы для получения валидированных данных.
 */
abstract class BaseOnboardingRequest extends FormRequest
{
    /**
     * Авторизация запроса.
     * TODO: Реализовать после MVP фазы.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Получить user_id из валидированных данных.
     */
    public function getUserId(): int
    {
        return (int) $this->validated('user_id');
    }

    /**
     * Получить session_id из валидированных данных.
     */
    public function getSessionId(): ?string
    {
        return $this->validated('session_id');
    }

    /**
     * Получить обязательный session_id из валидированных данных.
     */
    public function getRequiredSessionId(): string
    {
        return (string) $this->validated('session_id');
    }

    /**
     * Получить сообщение из валидированных данных.
     */
    public function getMessage(): ?string
    {
        return $this->validated('message');
    }

    /**
     * Общие сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID обязателен.',
            'user_id.integer' => 'User ID должен быть числом.',
            'user_id.min' => 'User ID должен быть положительным числом.',
            'session_id.uuid' => 'Session ID должен быть валидным UUID.',
            'session_id.exists' => 'Сессия не найдена.',
            'message.string' => 'Сообщение должно быть строкой.',
            'message.max' => 'Сообщение слишком длинное.',
        ];
    }
}
