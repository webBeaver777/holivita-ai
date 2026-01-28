<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос статуса обработки сообщения.
 */
class MessageStatusRequest extends FormRequest
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
            'session_id' => ['required', 'string', 'uuid'],
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
