<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OnboardingStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Модель сессии онбординга.
 *
 * @property string $id
 * @property int $user_id
 * @property OnboardingStatus $status
 * @property array|null $summary_json
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class OnboardingSession extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'user_id',
        'status',
        'summary_json',
        'completed_at',
    ];

    protected $casts = [
        'status' => OnboardingStatus::class,
        'summary_json' => 'array',
        'completed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => OnboardingStatus::IN_PROGRESS,
    ];

    /**
     * Связь с сообщениями.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(OnboardingMessage::class, 'session_id');
    }

    /**
     * Проверить, завершён ли онбординг.
     */
    public function isCompleted(): bool
    {
        return $this->status === OnboardingStatus::COMPLETED;
    }

    /**
     * Пометить как завершённый.
     *
     * @param  array<string, mixed>  $summary
     */
    public function markAsCompleted(array $summary): void
    {
        $this->update([
            'status' => OnboardingStatus::COMPLETED,
            'summary_json' => $summary,
            'completed_at' => now(),
        ]);
    }

    /**
     * Scope для активных сессий.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', OnboardingStatus::IN_PROGRESS);
    }

    /**
     * Scope для завершённых сессий.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', OnboardingStatus::COMPLETED);
    }
}
