<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'group',
        'description',
    ];

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * Scope to filter by group.
     */
    public function scopeByGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    /**
     * Get all permissions grouped.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, Permission>>
     */
    public static function allGrouped(): \Illuminate\Support\Collection
    {
        return static::all()->groupBy('group');
    }
}
