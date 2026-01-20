<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderType extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class, 'type_id');
    }

    /**
     * Get ID by code (cached).
     */
    public static function getIdByCode(string $code): ?int
    {
        return cache()->rememberForever("reminder_type_id_{$code}", function () use ($code) {
            return static::where('code', $code)->value('id');
        });
    }
}
