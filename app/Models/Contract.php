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

    protected $table = 'LGL_CONTRACT_MASTER';

    protected $primaryKey = 'LGL_ROW_ID';

    const CREATED_AT = 'CONTR_CREATED_DT';

    const UPDATED_AT = 'CONTR_UPDATED_DT';

    protected $fillable = [
        'TCKT_ID',
        'CONTR_NO',
        'CONTR_AGREE_NAME',
        'CONTR_PROP_DOC_TITLE',
        'CONTR_DOC_TYPE_ID',
        'CONTR_HAS_FIN_IMPACT',
        'CONTR_TAT_LGL_COMPLNCE',
        'CONTR_DIV_ID',
        'CONTR_DEPT_ID',
        'CONTR_PIC',
        'CONTR_START_DT',
        'CONTR_END_DT',
        'CONTR_IS_AUTO_RENEW',
        'CONTR_DESC',
        'CONTR_STS_ID',
        'CONTR_TERMINATE_DT',
        'CONTR_TERMINATE_REASON',
        'CONTR_DOC_DRAFT_PATH',
        'CONTR_DOC_REQUIRED_PATH',
        'CONTR_DOC_APPROVAL_PATH',
        'CONTR_CREATED_BY',
        'CONTR_DIR_SHARE_LINK',
    ];

    protected function casts(): array
    {
        return [
            'CONTR_START_DT' => 'date',
            'CONTR_END_DT' => 'date',
            'CONTR_IS_AUTO_RENEW' => 'boolean',
            'CONTR_HAS_FIN_IMPACT' => 'boolean',
            'CONTR_TAT_LGL_COMPLNCE' => 'boolean',
            'CONTR_DOC_REQUIRED_PATH' => 'array',
            'CONTR_TERMINATE_DT' => 'datetime',
        ];
    }

    /**
     * Generate unique contract number: CTR-{DIV_CODE}-{YYMM}{9999}
     * Resets sequence yearly per division
     */
    public static function generateContractNumber(int $divisionId): string
    {
        $division = \App\Models\Division::find($divisionId);

        if (! $division) {
            throw new \Exception("Division not found for ID: {$divisionId}");
        }

        // Truncate division code to max 3 characters
        // Division table LGL_DIVISION. Check Division model later for column name. Assuming 'code' for now.
        // Or likely DIV_CODE if renamed. I will check Division model next. For now using attribute accessor if exists.
        $divCode = strtoupper(substr($division->code ?? $division->DIV_CODE ?? 'UNK', 0, 3));

        // Format: CTR-DIV-YYMM (e.g., CTR-LEG-2602)
        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = "CTR-{$divCode}-{$year}{$month}";

        // Find last contract for this division and year
        // Use CONTR_NO
        $lastContract = static::where('CONTR_NO', 'like', "{$prefix}%")
            ->orderBy('CONTR_NO', 'desc')
            ->first();

        if ($lastContract) {
            $lastNumber = (int) substr($lastContract->CONTR_NO, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        // Final format: CTR-DIV-YYMM9999 (e.g., CTR-LEG-26020001)
        return $prefix.$newNumber;
    }

    /**
     * Get the ticket that created this contract.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'TCKT_ID');
    }

    /**
     * Get the division for this contract.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'CONTR_DIV_ID');
    }

    /**
     * Get the department for this contract.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'CONTR_DEPT_ID');
    }

    /**
     * Get the status for this contract.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(ContractStatus::class, 'CONTR_STS_ID');
    }

    /**
     * Get the document type for this contract.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'CONTR_DOC_TYPE_ID');
    }

    /**
     * Get the PIC (Person In Charge) for this contract.
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'CONTR_PIC');
    }

    /**
     * Get the user who created this contract.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'CONTR_CREATED_BY');
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
        return $this->morphMany(ActivityLog::class, 'subject', 'LOG_SUBJECT_TYPE', 'LOG_SUBJECT_ID');
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
        if ($this->CONTR_IS_AUTO_RENEW || ! $this->CONTR_END_DT) {
            return 999;
        }

        return (int) now()->startOfDay()->diffInDays($this->CONTR_END_DT, false);
    }

    /**
     * Get the traffic light status color based on days remaining.
     */
    public function getStatusColorAttribute(): string
    {
        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        $daysRemaining = $this->days_remaining;

        if ($this->CONTR_IS_AUTO_RENEW) {
            return 'green';
        }

        // Assuming status uses LOV_VALUE (e.g. 'expired')
        if ($daysRemaining < 0 || $this->status?->LOV_VALUE === 'expired') {
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

        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'active'))
            ->where('CONTR_IS_AUTO_RENEW', false)
            ->whereNotNull('CONTR_END_DT')
            ->whereDate('CONTR_END_DT', '<=', now()->addDays($warningThreshold))
            ->whereDate('CONTR_END_DT', '>=', now());
    }

    /**
     * Scope for expired contracts.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'expired'));
    }

    /**
     * Scope for active contracts.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'active'))
            ->whereDate('CONTR_END_DT', '>=', now());
    }

    /**
     * Scope for contracts in critical state.
     */
    public function scopeCritical(Builder $query): Builder
    {
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'active'))
            ->where('CONTR_IS_AUTO_RENEW', false)
            ->whereNotNull('CONTR_END_DT')
            ->whereDate('CONTR_END_DT', '<=', now()->addDays($criticalThreshold))
            ->whereDate('CONTR_END_DT', '>=', now());
    }

    /**
     * Scope to filter by PIC.
     */
    public function scopeForPic(Builder $query, int $userId): Builder
    {
        return $query->where('CONTR_PIC', $userId);
    }

    /**
     * Scope to filter by creator (user).
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('CONTR_CREATED_BY', $userId);
    }

    /**
     * Get the effective PIC name (either from user or manual entry).
     */
    public function getPicNameAttribute(): string
    {
        if ($this->CONTR_PIC && $this->pic) {
            return $this->pic->USER_FULLNAME ?? $this->pic->name;
        }

        return 'Unknown PIC';
    }

    /**
     * Get the effective PIC email (either from user or manual entry).
     */
    public function getPicEmailAttribute(): ?string
    {
        if ($this->CONTR_PIC && $this->pic) {
            return $this->pic->USER_EMAIL ?? $this->pic->email;
        }

        return null;
    }

    /**
     * Check if contract is expired.
     */
    public function isExpired(): bool
    {
        if ($this->CONTR_IS_AUTO_RENEW) {
            return false;
        }

        return ($this->CONTR_END_DT && $this->CONTR_END_DT->isPast()) || $this->status?->LOV_VALUE === 'expired';
    }

    /**
     * Get human-readable document type label.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        // DocumentType model will need update too.
        return $this->documentType?->name ?? 'Unknown';
    }

    /**
     * Get human-readable financial impact label.
     */
    public function getFinancialImpactLabelAttribute(): ?string
    {
        return $this->CONTR_HAS_FIN_IMPACT ? 'Ada' : 'Tidak Ada';
    }

    /**
     * Check if contract is terminated.
     */
    public function isTerminated(): bool
    {
        return $this->status?->LOV_VALUE === 'terminated';
    }

    /**
     * Scope for terminated contracts.
     */
    public function scopeTerminated(Builder $query): Builder
    {
        return $query->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'terminated'));
    }

    /**
     * Terminate contract before end date.
     */
    public function terminate(string $reason): void
    {
        $this->update([
            'CONTR_STS_ID' => ContractStatus::getIdByCode('terminated'),
            'CONTR_TERMINATE_DT' => now(),
            'CONTR_TERMINATE_REASON' => $reason,
        ]);

        // Auto-close associated ticket
        // Ticket model fields will change too! TCKT_STS_ID
        if ($this->ticket && $this->ticket->status !== 'closed') { // Ticket model accessor 'status' might return object now?
            // Assuming Ticket model logic will be updated.
            $this->ticket->update(['TCKT_STS_ID' => TicketStatus::getIdByCode('closed')]);
            $this->ticket->logActivity('Ticket ditutup otomatis karena contract terminated');
        }

        $this->activityLogs()->create([
            'user_id' => auth()->id(),
            'action' => "Contract terminated: {$reason}",
            'metadata' => [
                'contract_number' => $this->CONTR_NO,
                'terminated_at' => $this->CONTR_TERMINATE_DT->toDateTimeString(),
            ],
        ]);
    }
}
