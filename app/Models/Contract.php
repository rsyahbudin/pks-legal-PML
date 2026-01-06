<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_number',
        'partner_id',
        'division_id',
        'pic_id',
        'pic_name',
        'pic_email',
        'start_date',
        'end_date',
        'description',
        'status',
        'document_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Get the partner for this contract.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Get the division for this contract.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the PIC (Person In Charge) for this contract.
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    /**
     * Get the user who created this contract.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reminder logs for this contract.
     */
    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    /**
     * Get the activity logs for this contract.
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }

    /**
     * Get the notifications for this contract.
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    /**
     * Get the days remaining until contract end.
     */
    public function getDaysRemainingAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->end_date, false);
    }

    /**
     * Get the traffic light status color based on days remaining.
     * green: > warning threshold days
     * yellow: between critical and warning threshold days
     * red: < critical threshold days or expired
     */
    public function getStatusColorAttribute(): string
    {
        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        $daysRemaining = $this->days_remaining;

        if ($daysRemaining < 0 || $this->status === 'expired') {
            return 'red';
        }

        if ($daysRemaining <= $criticalThreshold) {
            return 'red';
        }

        if ($daysRemaining <= $warningThreshold) {
            return 'yellow';
        }

        return 'green';
    }

    /**
     * Get the human-readable status color label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status_color) {
            'green' => 'Aman',
            'yellow' => 'Mendekati Expired',
            'red' => 'Kritis / Expired',
            default => 'Unknown',
        };
    }

    /**
     * Scope for contracts expiring soon (within warning threshold).
     */
    public function scopeExpiringSoon(Builder $query): Builder
    {
        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);

        return $query->where('status', 'active')
            ->whereDate('end_date', '<=', now()->addDays($warningThreshold))
            ->whereDate('end_date', '>=', now());
    }

    /**
     * Scope for expired contracts.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
                ->orWhereDate('end_date', '<', now());
        });
    }

    /**
     * Scope for active contracts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereDate('end_date', '>=', now());
    }

    /**
     * Scope for contracts in critical state.
     */
    public function scopeCritical(Builder $query): Builder
    {
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        return $query->where('status', 'active')
            ->whereDate('end_date', '<=', now()->addDays($criticalThreshold))
            ->whereDate('end_date', '>=', now());
    }

    /**
     * Scope to filter by PIC.
     */
    public function scopeForPic(Builder $query, int $userId): Builder
    {
        return $query->where('pic_id', $userId);
    }

    /**
     * Get the effective PIC name (either from user or manual entry).
     */
    public function getPicNameAttribute(): string
    {
        if ($this->pic_id && $this->pic) {
            return $this->pic->name;
        }
        return $this->attributes['pic_name'] ?? 'Unknown PIC';
    }

    /**
     * Get the effective PIC email (either from user or manual entry).
     */
    public function getPicEmailAttribute(): ?string
    {
        if ($this->pic_id && $this->pic) {
            return $this->pic->email;
        }
        return $this->attributes['pic_email'] ?? null;
    }

    /**
     * Check if contract is expired.
     */
    public function isExpired(): bool
    {
        return $this->end_date->isPast() || $this->status === 'expired';
    }
}
