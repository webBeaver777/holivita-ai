<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OnboardingStatus;
use Illuminate\Database\Eloquent\Builder;
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
 *
 * @method static Builder|OnboardingSession forUser(int $userId)
 * @method static Builder|OnboardingSession inProgress()
 * @method static Builder|OnboardingSession completed()
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

    public function messages(): HasMany
    {
        return $this->hasMany(OnboardingMessage::class, 'session_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === OnboardingStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === OnboardingStatus::CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status === OnboardingStatus::EXPIRED;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
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

    public function markAsCancelled(): void
    {
        $this->update([
            'status' => OnboardingStatus::CANCELLED,
            'completed_at' => now(),
        ]);
    }

    public function markAsExpired(): void
    {
        $this->update([
            'status' => OnboardingStatus::EXPIRED,
            'completed_at' => now(),
        ]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', OnboardingStatus::IN_PROGRESS);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', OnboardingStatus::COMPLETED);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', OnboardingStatus::CANCELLED);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', OnboardingStatus::EXPIRED);
    }

    public function scopeStale(Builder $query, int $hours = 24): Builder
    {
        return $query->inProgress()
            ->where('updated_at', '<', now()->subHours($hours));
    }
}
