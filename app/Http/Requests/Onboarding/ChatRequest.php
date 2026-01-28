<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

/**
 * Запрос для отправки сообщения в чат онбординга.
 */
class ChatRequest extends BaseOnboardingRequest
{
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
}
