<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_en',
        'description',
        'requires_contract',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_contract' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get document type ID by code (cached).
     */
    public static function getIdByCode(string $code): ?int
    {
        return cache()->remember("document_type_{$code}", 3600, function () use ($code) {
            return static::where('code', $code)->value('id');
        });
    }

    /**
     * Get tickets with this document type.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Get contracts with this document type.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Scope to only get active document types.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('name');
    }

    /**
     * Check if this document type requires contract creation.
     */
    public function requiresContract(): bool
    {
        return $this->requires_contract;
    }

    /**
     * Get the icon name for UI rendering.
     */
    public function getIconAttribute($value): string
    {
        return $value ?? 'document';
    }

    /**
     * Get the color for UI rendering.
     */
    public function getColorAttribute($value): string
    {
        return $value ?? 'neutral';
    }
}
