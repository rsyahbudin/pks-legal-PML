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

    protected $table = 'LGL_NOTIFICATION_MASTER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_NOTIF_CREATED_DT';

    const UPDATED_AT = 'REF_NOTIF_UPDATED_DT';

    protected $fillable = [
        'user_id',
        'NOTIFICATION_TYPE',
        'NOTIF_TITLE',
        'NOTIF_MSG',
        'NOTIFIABLE_TYPE',
        'NOTIFIABLE_ID',
        'READ_AT',
    ];

    protected function casts(): array
    {
        return [
            'READ_AT' => 'datetime',
            'REF_NOTIF_CREATED_DT' => 'datetime',
            'REF_NOTIF_UPDATED_DT' => 'datetime',
        ];
    }

    public function getCreatedAtAttribute()
    {
        return $this->REF_NOTIF_CREATED_DT;
    }

    public function getUpdatedAtAttribute()
    {
        return $this->REF_NOTIF_UPDATED_DT;
    }

    /**
     * Get the user this notification belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the notifiable entity (contract, partner, etc).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo(null, 'NOTIFIABLE_TYPE', 'NOTIFIABLE_ID');
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->READ_AT !== null;
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->update(['READ_AT' => now()]);
        }
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['READ_AT' => null]);
    }

    /**
     * Scope for unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('READ_AT');
    }

    /**
     * Scope for read notifications.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('READ_AT');
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
            'user_id' => $user->LGL_ROW_ID,
            'NOTIFICATION_TYPE' => $type,
            'NOTIF_TITLE' => $title,
            'NOTIF_MSG' => "Kontrak {$contract->CONTR_NO} ({$contract->CONTR_AGREE_NAME}) membutuhkan perhatian Anda.",
            'NOTIFIABLE_TYPE' => Contract::class,
            'NOTIFIABLE_ID' => $contract->LGL_ROW_ID,
        ]);
    }
}
