<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'division_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the role for this user.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the division for this user.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get contracts assigned to this user as PIC.
     */
    public function assignedContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'pic_id');
    }

    /**
     * Get contracts created by this user.
     */
    public function createdContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'created_by');
    }

    /**
     * Get notifications for this user.
     */
    public function internalNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadNotificationsCountAttribute(): int
    {
        return $this->internalNotifications()->unread()->count();
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $slug): bool
    {
        return $this->role && $this->role->slug === $slug;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $slugs): bool
    {
        return $this->role && in_array($this->role->slug, $slugs);
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $slug): bool
    {
        if (! $this->role) {
            return false;
        }

        // Super admin has all permissions
        if ($this->role->slug === 'super-admin') {
            return true;
        }

        return $this->role->hasPermission($slug);
    }

    /**
     * Check if user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Check if user is legal team.
     */
    public function isLegal(): bool
    {
        return $this->hasRole('legal');
    }

    /**
     * Check if user is a PIC.
     */
    public function isPic(): bool
    {
        return $this->hasRole('pic');
    }

    /**
     * Check if user is management.
     */
    public function isManagement(): bool
    {
        return $this->hasRole('management');
    }

    /**
     * Check if user can manage other users.
     */
    public function canManageUsers(): bool
    {
        return $this->hasPermission('users.manage');
    }

    /**
     * Check if user can manage contracts.
     */
    public function canManageContracts(): bool
    {
        return $this->hasPermission('contracts.create') || $this->hasPermission('contracts.edit');
    }
}
