<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    use HasFactory;

    protected $table = 'LGL_REMINDER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_REM_CREATED_DT';

    const UPDATED_AT = 'REF_REM_UPDATED_DT';

    protected $fillable = [
        'LGL_ROW_ID_CONTRACT', // Renamed contract_id
        'user_id', // Not renamed
        'type_id', // Not renamed
        'days_remaining', // Not renamed
        'REMINDER_DATE', // Renamed (assumed)
        'SENT_AT',
        'IS_SENT',
        'ERROR_MESSAGE',
    ];

    protected function casts(): array
    {
        return [
            'SENT_AT' => 'datetime',
            'REMINDER_DATE' => 'date',
            'IS_SENT' => 'boolean',
        ];
    }

    /**
     * Get the contract for this reminder log.
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'LGL_ROW_ID_CONTRACT');
    }

    /**
     * Get the user who received this reminder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

        // Using new column LGL_ROW_ID_CONTRACT and SENT_AT
        return static::where('LGL_ROW_ID_CONTRACT', $contractId)
            ->where('user_id', $userId)
            ->where('type_id', $typeId)
            ->whereDate('SENT_AT', today())
            ->exists();
    }

    /**
     * Log a sent reminder.
     */
    public static function logReminder(Contract $contract, User $user, string $typeCode, int $daysRemaining): self
    {
        $typeId = \App\Models\ReminderType::getIdByCode($typeCode);

        // Uses LGL_ROW_ID for contract/user (User PK is LGL_ROW_ID)
        return static::create([
            'LGL_ROW_ID_CONTRACT' => $contract->LGL_ROW_ID,
            'user_id' => $user->LGL_ROW_ID,
            'type_id' => $typeId,
            'days_remaining' => $daysRemaining,
            'SENT_AT' => now(),
            'IS_SENT' => true,
        ]);
    }
}
