<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageRole;
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
 * @property \Carbon\Carbon $created_at
 */
class OnboardingMessage extends Model
{
    use HasFactory;

    /**
     * Отключаем updated_at, так как сообщения не редактируются.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'session_id',
        'role',
        'content',
    ];

    protected $casts = [
        'role' => MessageRole::class,
    ];

    /**
     * Связь с сессией.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(OnboardingSession::class, 'session_id');
    }

    /**
     * Проверить, от пользователя ли сообщение.
     */
    public function isFromUser(): bool
    {
        return $this->role === MessageRole::USER;
    }

    /**
     * Проверить, от ассистента ли сообщение.
     */
    public function isFromAssistant(): bool
    {
        return $this->role === MessageRole::ASSISTANT;
    }

    /**
     * Преобразовать в формат для AI.
     *
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
