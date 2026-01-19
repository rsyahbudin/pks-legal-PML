<?php

namespace App\Models;

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
        'ticket_id',
        'contract_number',
        'agreement_name',
        'proposed_document_title',
        'document_type',
        'financial_impact',
        'tat_legal_compliance',
        'division_id',
        'department_id',
        'pic_id',
        'pic_name',
        'pic_email',
        'start_date',
        'end_date',
        'is_auto_renewal',
        'description',
        'status',
        'terminated_at',
        'termination_reason',
        'document_path',
        'draft_document_path',
        'mandatory_documents_path',
        'approval_document_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_auto_renewal' => 'boolean',
            'tat_legal_compliance' => 'boolean',
            'mandatory_documents_path' => 'array',
            'terminated_at' => 'datetime',
        ];
    }

    /**
     * Generate unique contract number: CTR-YYYY-MM-XXXX
     */
    public static function generateContractNumber(): string
    {
        $prefix = 'CTR-' . now()->format('Y-m');
        $lastContract = static::where('contract_number', 'like', $prefix . '-%')
            ->orderBy('contract_number', 'desc')
            ->first();

        if ($lastContract) {
            $lastNumber = (int) substr($lastContract->contract_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix . '-' . $newNumber;
    }

    /**
     * Get the ticket that created this contract.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the division for this contract.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the department for this contract.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
        if ($this->is_auto_renewal || ! $this->end_date) {
            return 999; // Treat as far in the future or handle as needed
        }

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

        if ($this->is_auto_renewal) {
            return 'green';
        }

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
            ->where('is_auto_renewal', false)
            ->whereNotNull('end_date')
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
            ->where('is_auto_renewal', false)
            ->whereNotNull('end_date')
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
        if ($this->is_auto_renewal) {
            return false;
        }

        return ($this->end_date && $this->end_date->isPast()) || $this->status === 'expired';
    }

    /**
     * Get human-readable document type label.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'perjanjian' => 'Perjanjian/Adendum/Amandemen',
            'nda' => 'Perjanjian Kerahasiaan (NDA)',
            'surat_kuasa' => 'Surat Kuasa',
            'pendapat_hukum' => 'Pendapat Hukum',
            'surat_pernyataan' => 'Surat Pernyataan',
            'surat_lainnya', 'lainnya' => 'Surat Lainnya',
            default => $this->document_type,
        };
    }

    /**
     * Get human-readable financial impact label.
     */
    public function getFinancialImpactLabelAttribute(): ?string
    {
        if (! $this->financial_impact) {
            return null;
        }

        return match ($this->financial_impact) {
            'income' => 'Income (Pemasukan)',
            'expenditure' => 'Expenditure (Pengeluaran)',
            default => $this->financial_impact,
        };
    }

    /**
     * Check if contract is terminated.
     */
    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    /**
     * Scope for terminated contracts.
     */
    public function scopeTerminated(Builder $query): Builder
    {
        return $query->where('status', 'terminated');
    }

    /**
     * Terminate contract before end date.
     */
    public function terminate(string $reason): void
    {
        $this->update([
            'status' => 'terminated',
            'terminated_at' => now(),
            'termination_reason' => $reason,
        ]);

        // Auto-close associated ticket
        if ($this->ticket && $this->ticket->status !== 'closed') {
            $this->ticket->update(['status' => 'closed']);
            $this->ticket->logActivity('Ticket ditutup otomatis karena contract terminated');
        }

        $this->activityLogs()->create([
            'user_id' => auth()->id(),
            'action' => "Contract terminated: {$reason}",
            'metadata' => [
                'contract_number' => $this->contract_number,
                'terminated_at' => $this->terminated_at->toDateTimeString(),
            ],
        ]);
    }
}
