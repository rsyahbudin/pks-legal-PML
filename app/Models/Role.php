<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /**
     * Get the users for this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the permissions for this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * Check if role has a specific permission.
     */
    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    /**
     * Give a permission to this role.
     */
    public function givePermission(Permission|int|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->firstOrFail();
        }

        if (is_int($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        $this->permissions()->syncWithoutDetaching([$permission->id]);
    }

    /**
     * Revoke a permission from this role.
     */
    public function revokePermission(Permission|int|string $permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->firstOrFail();
        }

        if (is_int($permission)) {
            $permission = Permission::findOrFail($permission);
        }

        $this->permissions()->detach($permission->id);
    }

    /**
     * Sync permissions for this role.
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }
}
