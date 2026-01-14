<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'division_id',
        'department_id',
        'has_financial_impact',
        'proposed_document_title',
        'draft_document_path',
        'document_type',
        // Conditional: perjanjian/nda
        'counterpart_name',
        'agreement_start_date',
        'agreement_duration',
        'is_auto_renewal',
        'renewal_period',
        'renewal_notification_days',
        'agreement_end_date',
        'termination_notification_days',
        // Conditional: surat_kuasa
        'kuasa_pemberi',
        'kuasa_penerima',
        'kuasa_start_date',
        'kuasa_end_date',
        // Common
        'tat_legal_compliance',
        'mandatory_documents_path',
        'approval_document_path',
        // Workflow
        'status',
        'reviewed_at',
        'reviewed_by',
        'aging_start_at',
        'aging_end_at',
        'aging_duration',
        'rejection_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'has_financial_impact' => 'boolean',
            'is_auto_renewal' => 'boolean',
            'tat_legal_compliance' => 'boolean',
            'mandatory_documents_path' => 'array',
            'agreement_start_date' => 'date',
            'agreement_end_date' => 'date',
            'kuasa_start_date' => 'date',
            'kuasa_end_date' => 'date',
            'reviewed_at' => 'datetime',
            'aging_start_at' => 'datetime',
            'aging_end_at' => 'datetime',
        ];
    }

    /**
     * Boot method to auto-generate ticket number.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (! $ticket->ticket_number) {
                $ticket->ticket_number = static::generateTicketNumber();
            }
        });
    }

    /**
     * Generate unique ticket number: TKT-YYYY-MM-XXXX
     */
    public static function generateTicketNumber(): string
    {
        $prefix = 'TKT-'.now()->format('Y-m');
        $lastTicket = static::where('ticket_number', 'like', $prefix.'-%')
            ->orderBy('ticket_number', 'desc')
            ->first();

        if ($lastTicket) {
            $lastNumber = (int) substr($lastTicket->ticket_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix.'-'.$newNumber;
    }

    /**
     * Get the division for this ticket.
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the department for this ticket.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the user who created this ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the legal user who reviewed this ticket.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the contract created from this ticket (if approved).
     */
    public function contract(): HasOne
    {
        return $this->hasOne(Contract::class);
    }

    /**
     * Get the activity logs for this ticket.
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }

    /**
     * Scope for open tickets.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope for tickets that need review (open status).
     */
    public function scopeNeedReview(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope for on process tickets.
     */
    public function scopeOnProcess(Builder $query): Builder
    {
        return $query->where('status', 'on_process');
    }

    /**
     * Scope for done tickets.
     */
    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', 'done');
    }

    /**
     * Scope for rejected tickets.
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for closed tickets.
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    /**
     * Scope for tickets created by a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Check if ticket can be reviewed.
     */
    public function canBeReviewed(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Move ticket to "on_process" status.
     */
    public function moveToOnProcess(User $reviewer): void
    {
        $this->update([
            'status' => 'on_process',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'aging_start_at' => now(),
        ]);

        $this->logActivity('Ticket diubah ke status On Process', $reviewer);
    }

    /**
     * Move ticket to "done" status and calculate aging.
     */
    public function moveToDone(): void
    {
        $agingEnd = now();
        $agingDuration = null;

        if ($this->aging_start_at) {
            $agingDuration = $this->aging_start_at->diffInMinutes($agingEnd);
        }

        $this->update([
            'status' => 'done',
            'aging_end_at' => $agingEnd,
            'aging_duration' => $agingDuration,
        ]);

        $this->logActivity('Ticket diselesaikan (Done)');
    }

    /**
     * Reject ticket with reason.
     */
    public function reject(string $reason, User $reviewer): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'rejection_reason' => $reason,
        ]);

        $this->logActivity("Ticket ditolak: {$reason}", $reviewer);
    }

    /**
     * Move ticket directly to "closed" status for non-contractable documents.
     */
    public function moveToClosedDirectly(): void
    {
        $agingEnd = now();
        $agingDuration = null;

        if ($this->aging_start_at) {
            $agingDuration = $this->aging_start_at->diffInMinutes($agingEnd);
        }

        $this->update([
            'status' => 'closed',
            'aging_end_at' => $agingEnd,
            'aging_duration' => $agingDuration,
        ]);

        $this->logActivity('Ticket ditutup (tidak memerlukan contract)');
    }

    // ... (skipped some methods)

    /**
     * Get human-readable aging duration.
     */
    public function getAgingDurationDisplayAttribute(): ?string
    {
        if (! $this->aging_duration) {
            return null;
        }

        // $this->aging_duration is now in MINUTES
        $minutes = $this->aging_duration;
        $days = floor($minutes / 1440);
        $hours = floor(($minutes % 1440) / 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days} hari";
        if ($hours > 0) $parts[] = "{$hours} jam";
        if ($mins > 0 || empty($parts)) $parts[] = "{$mins} menit";

        return implode(' ', $parts);
    }

    /**
     * Get document type label.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return match ($this->document_type) {
            'perjanjian' => 'Perjanjian/Adendum/Amandemen',
            'nda' => 'Perjanjian Kerahasiaan (NDA)',
            'surat_kuasa' => 'Surat Kuasa',
            'pendapat_hukum' => 'Pendapat Hukum',
            'surat_pernyataan' => 'Surat Pernyataan',
            'surat_lainnya' => 'Surat Lainnya',
            default => $this->document_type,
        };
    }

    /**
     * Get status label (human-readable).
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'on_process' => 'On Process',
            'done' => 'Done',
            'rejected' => 'Rejected',
            'closed' => 'Closed',
            default => $this->status,
        };
    }

    /**
     * Get status color for badge.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => 'blue',
            'on_process' => 'yellow',
            'done' => 'green',
            'rejected' => 'red',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Log activity for this ticket.
     */
    public function logActivity(string $message, ?User $user = null): void
    {
        $this->activityLogs()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $message,
            'metadata' => [
                'ticket_number' => $this->ticket_number,
                'status' => $this->status,
            ],
        ]);
    }
}
