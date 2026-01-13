<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'notifiable_type',
        'notifiable_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * Get the user this notification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the notifiable entity (contract, partner, etc).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope for read notifications.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope for notifications of a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Create a contract reminder notification.
     */
    public static function createContractReminder(User $user, Contract $contract, int $daysRemaining): self
    {
        $type = $daysRemaining <= 0 ? 'contract_expired' : 'contract_expiring';
        $title = $daysRemaining <= 0
            ? 'Kontrak Sudah Expired'
            : "Kontrak Akan Expired dalam {$daysRemaining} Hari";

        return static::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => "Kontrak {$contract->contract_number} ({$contract->agreement_name}) membutuhkan perhatian Anda.",
            'notifiable_type' => Contract::class,
            'notifiable_id' => $contract->id,
        ]);
    }
}
