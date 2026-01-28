<?php

declare(strict_types=1);

namespace App\Http\Requests\Summary;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Запрос списка суммаризаций.
 */
class SummaryIndexRequest extends FormRequest
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
            'user_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function getUserId(): ?int
    {
        $userId = $this->validated('user_id');

        return $userId !== null ? (int) $userId : null;
    }

    public function getPerPage(int $default = 15): int
    {
        return (int) ($this->validated('per_page') ?? $default);
    }
}
