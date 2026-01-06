<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'loggable_type',
        'loggable_id',
        'old_values',
        'new_values',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Get the user who performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the loggable entity.
     */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Log a create action.
     */
    public static function logCreated(Model $model, ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => 'created',
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
            'new_values' => $model->toArray(),
        ]);
    }

    /**
     * Log an update action.
     */
    public static function logUpdated(Model $model, array $oldValues, ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => 'updated',
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $model->toArray(),
        ]);
    }

    /**
     * Log a delete action.
     */
    public static function logDeleted(Model $model, ?int $userId = null): self
    {
        return static::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => 'deleted',
            'loggable_type' => get_class($model),
            'loggable_id' => $model->id,
            'old_values' => $model->toArray(),
        ]);
    }

    /**
     * Get action label in Indonesian.
     */
    public function getActionLabelAttribute(): string
    {
        return match ($this->action) {
            'created' => 'Dibuat',
            'updated' => 'Diperbarui',
            'deleted' => 'Dihapus',
            default => $this->action,
        };
    }
}
