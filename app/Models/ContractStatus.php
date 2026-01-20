<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractStatus extends Model
{
    protected $fillable = ['code', 'name', 'name_id', 'color', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get status ID by code (cached for performance).
     */
    public static function getIdByCode(string $code): ?int
    {
        return cache()->remember("contract_status_{$code}", 3600, function () use ($code) {
            return static::where('code', $code)->value('id');
        });
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'status_id');
    }

    /**
     * Scope a query to only include active statuses.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
