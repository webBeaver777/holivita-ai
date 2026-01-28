<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

/**
 * Запрос истории чата.
 */
class HistoryRequest extends BaseOnboardingRequest
{
    /**
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
