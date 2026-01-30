<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'user_id',
        'type_id',
        'days_remaining',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Get the contract for this reminder log.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * Get the user who received this reminder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the reminder type.
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ReminderType::class, 'type_id');
    }

    /**
     * Check if a reminder was already sent today.
     */
    public static function wasSentToday(int $contractId, int $userId, string $typeCode): bool
    {
        $typeId = \App\Models\ReminderType::getIdByCode($typeCode);

        return static::where('contract_id', $contractId)
            ->where('user_id', $userId)
            ->where('type_id', $typeId)
            ->whereDate('sent_at', today())
            ->exists();
    }

    /**
     * Log a sent reminder.
     */
    public static function logReminder(Contract $contract, User $user, string $typeCode, int $daysRemaining): self
    {
        $typeId = \App\Models\ReminderType::getIdByCode($typeCode);

        return static::create([
            'contract_id' => $contract->id,
            'user_id' => $user->id,
            'type_id' => $typeId,
            'days_remaining' => $daysRemaining,
            'sent_at' => now(),
        ]);
    }
}
