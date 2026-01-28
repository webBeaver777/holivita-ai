<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Модель сообщения онбординга.
 *
 * @property int $id
 * @property string $session_id
 * @property MessageRole $role
 * @property string $content
 * @property MessageStatus $status
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 */
class OnboardingMessage extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id',
        'role',
        'content',
        'status',
        'error_message',
    ];

    protected $casts = [
        'role' => MessageRole::class,
        'status' => MessageStatus::class,
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OnboardingSession::class, 'session_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::PENDING);
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::PROCESSING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::FAILED);
    }

    public function scopeInProgress(Builder $query): Builder
    {
        return $query->whereIn('status', [MessageStatus::PENDING, MessageStatus::PROCESSING]);
    }

    public function scopeAssistant(Builder $query): Builder
    {
        return $query->where('role', MessageRole::ASSISTANT);
    }

    public function scopeUser(Builder $query): Builder
    {
        return $query->where('role', MessageRole::USER);
    }

    public function isPending(): bool
    {
        return $this->status === MessageStatus::PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->status === MessageStatus::PROCESSING;
    }

    public function isCompleted(): bool
    {
        return $this->status === MessageStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === MessageStatus::FAILED;
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => MessageStatus::PROCESSING]);
    }

    public function markAsCompleted(?string $content = null): void
    {
        $data = ['status' => MessageStatus::COMPLETED];

        if ($content !== null) {
            $data['content'] = $content;
        }

        $this->update($data);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => MessageStatus::FAILED,
            'error_message' => $error,
        ]);
    }

    /**
     * @return array{role: string, content: string}
     */
    public function toAIFormat(): array
    {
        return [
            'role' => $this->role->value,
            'content' => $this->content,
        ];
    }
}
