<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialImpact extends Model
{
    protected $fillable = ['code', 'name', 'name_id', 'color', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get ID by code (cached).
     */
    public static function getIdByCode(string $code): ?int
    {
        return cache()->remember("financial_impact_{$code}", 3600, function () use ($code) {
            return static::where('code', $code)->value('id');
        });
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'financial_impact_id');
    }
}
