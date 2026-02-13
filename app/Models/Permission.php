<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'LGL_PERMISSION';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'REF_PERM_CREATED_DT';

    const UPDATED_AT = 'REF_PERM_UPDATED_DT';

    protected $fillable = [
        'PERMISSION_ID', // String ID added by migration
        'PERMISSION_NAME',
        'PERMISSION_CODE', // Assuming code exists standard
        'PERMISSION_GROUP', // Assuming group exists standard
        'PERMISSION_DESC', // Assuming description exists standard
    ];

    /**
     * Get the roles that have this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'LGL_ROLE_PERMISSION', 'PERMISSION_ID', 'ROLE_ID');
    }

    /**
     * Scope to filter by group.
     */
    public function scopeByGroup(Builder $query, string $group): Builder
    {
        return $query->where('PERMISSION_GROUP', $group);
    }

    /**
     * Get all permissions grouped.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Database\Eloquent\Collection<int, Permission>>
     */
    public static function allGrouped(): \Illuminate\Support\Collection
    {
        return static::all()->groupBy('PERMISSION_GROUP');
    }
}
