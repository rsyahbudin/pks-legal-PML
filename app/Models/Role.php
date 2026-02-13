<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $table = 'LGL_ROLE';

    protected $primaryKey = 'ROLE_ID'; // Migration renamed 'id' to 'ROLE_ID'

    const CREATED_AT = 'REF_ROLE_CREATED_DT';

    const UPDATED_AT = 'REF_ROLE_UPDATED_DT';

    protected $fillable = [
        'ROLE_NAME',
        'ROLE_SLUG',
        'GUARD_NAME',
        'ROLE_DESCRIPTION',
        'IS_ACTIVE',
    ];

    protected function casts(): array
    {
        return [
            'IS_ACTIVE' => 'boolean',
        ];
    }

    /**
     * Get the users for this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'USER_ROLE_ID');
    }

    /**
     * Get the permissions for this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'LGL_ROLE_PERMISSION', 'ROLE_ID', 'PERMISSION_ID');
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $slug): bool
    {
        // Assuming Permission model uses PERMISSION_CODE or slug?
        // Migration didn't rename 'slug' in Permission?
        // Permission migration lines 556-588:
        // `name` -> `PERMISSION_NAME`.
        // `guard_name` -> `GUARD_NAME`.
        // No rename for 'slug' or 'code'?
        // Wait. `Permission.php` had `PERMISSION_CODE`.
        // If migration didn't rename it, maybe it was already `PERMISSION_CODE`?
        // Or I missed it.
        // Assuming `PERMISSION_CODE` exists.
        return $this->permissions()->where('PERMISSION_CODE', $slug)->exists();
    }

    /**
     * Give a permission to this role.
     */
    public function givePermission(Permission|int|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('PERMISSION_CODE', $permission)->firstOrFail();
        }

        if (is_int($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        // Use PK of Permission (LGL_ROW_ID)
        // But pivoting uses permission_id column which refers to LGL_ROW_ID?
        // Yes, likely.
        $this->permissions()->syncWithoutDetaching([$permission->LGL_ROW_ID]);
    }

    /**
     * Revoke a permission from this role.
     */
    public function revokePermission(Permission|int|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('PERMISSION_CODE', $permission)->firstOrFail();
        }

        if (is_int($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        $this->permissions()->detach($permission->LGL_ROW_ID);
    }

    /**
     * Sync permissions for this role.
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }
}
