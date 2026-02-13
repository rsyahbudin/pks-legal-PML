<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractStatus extends Model
{
    protected $table = 'LGL_LOV_MASTER';

    protected $primaryKey = 'LGL_ID';

    const CREATED_AT = 'LOV_CREATED_DT';

    const UPDATED_AT = 'LOV_UPDATED_DT';

    protected $fillable = [
        'LOV_TYPE',
        'LOV_VALUE',
        'LOV_DISPLAY_NAME',
        'LOV_SEQ_NO',
        'DESCRIPTION',
        'IS_ACTIVE',
    ];

    protected function casts(): array
    {
        return [
            'IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get the color for the badge.
     */
    public function getColorAttribute(): string
    {
        return match ($this->LOV_VALUE) {
            'active' => 'green',
            'expired' => 'red',
            'terminated' => 'red',
            'closed' => 'gray',
            'draft' => 'blue',
            default => 'neutral',
        };
    }

    /**
     * Get the display name.
     */
    public function getNameAttribute(): string
    {
        return $this->LOV_DISPLAY_NAME ?? $this->LOV_VALUE ?? 'Unknown';
    }

    /**
     * Get status ID by code (cached for performance).
     */
    public static function getIdByCode(string $code): ?int
    {
        return cache()->remember("contract_status_{$code}", 3600, function () use ($code) {
            return static::where('LOV_TYPE', 'CONTRACT_STATUS')
                ->where('LOV_VALUE', $code)
                ->value('LGL_ID');
        });
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'CONTR_STS_ID');
    }

    /**
     * Scope a query to only include active statuses.
     */
    public function scopeActive($query)
    {
        return $query->where('IS_ACTIVE', true);
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('contract_status', function ($builder) {
            $builder->where('LOV_TYPE', 'CONTRACT_STATUS');
        });
    }
}
