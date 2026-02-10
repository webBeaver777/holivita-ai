<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Модель для отслеживания асинхронных транскрипций голоса.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $session_id
 * @property string|null $provider
 * @property string $language
 * @property string $original_filename
 * @property string $stored_path
 * @property string $mime_type
 * @property int $file_size
 * @property MessageStatus $status
 * @property string|null $transcribed_text
 * @property float|null $confidence
 * @property float|null $duration
 * @property string|null $error_message
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
final class VoiceTranscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'session_id',
        'provider',
        'language',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'status',
        'transcribed_text',
        'confidence',
        'duration',
        'error_message',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'file_size' => 'integer',
        'status' => MessageStatus::class,
        'confidence' => 'float',
        'duration' => 'float',
    ];

    // === SCOPES ===

    /**
     * @param  Builder<VoiceTranscription>  $query
     * @return Builder<VoiceTranscription>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::PENDING);
    }

    /**
     * @param  Builder<VoiceTranscription>  $query
     * @return Builder<VoiceTranscription>
     */
    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::PROCESSING);
    }

    /**
     * @param  Builder<VoiceTranscription>  $query
     * @return Builder<VoiceTranscription>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::COMPLETED);
    }

    /**
     * @param  Builder<VoiceTranscription>  $query
     * @return Builder<VoiceTranscription>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', MessageStatus::FAILED);
    }

    // === STATUS METHODS ===

    public function markAsProcessing(): void
    {
        $this->update(['status' => MessageStatus::PROCESSING]);
    }

    public function markAsCompleted(
        string $text,
        ?string $provider = null,
        ?float $confidence = null,
        ?float $duration = null,
    ): void {
        $this->update([
            'status' => MessageStatus::COMPLETED,
            'transcribed_text' => $text,
            'provider' => $provider ?? $this->provider,
            'confidence' => $confidence,
            'duration' => $duration,
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => MessageStatus::FAILED,
            'error_message' => $error,
        ]);
    }

    // === HELPERS ===

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

    public function isFinished(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    public function hasText(): bool
    {
        return ! empty(trim($this->transcribed_text ?? ''));
    }

    /**
     * Получить полный путь к сохранённому файлу.
     */
    public function getFullStoredPath(): string
    {
        $disk = Storage::disk((string) config('voice.storage_disk', 'local'));

        return $disk->path($this->stored_path);
    }
}
