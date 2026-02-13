<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReminderType extends Model
{
    use HasFactory;

    protected $table = 'LGL_REF_REMINDER_TYPE';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_REMIND_TYPE_CREATED_DT';

    const UPDATED_AT = 'REF_REMIND_TYPE_UPDATED_DT';

    protected $fillable = [
        'REF_REMIND_TYPE_ID',
        'REF_REMIND_TYPE_NAME',
        'REF_REMIND_TYPE_IS_ACTIVE',
        'REF_REMIND_TYPE_CREATED_BY',
        'REF_REMIND_TYPE_UPDATED_BY',
    ];

    protected function casts(): array
    {
        return [
            'REF_REMIND_TYPE_IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get ID by code (cached).
     */
    public static function getIdByCode(string $code): ?int
    {
        return cache()->rememberForever("reminder_type_id_{$code}", function () use ($code) {
            return static::where('REF_REMIND_TYPE_ID', $code)->value('LGL_ROW_ID');
        });
    }
}
