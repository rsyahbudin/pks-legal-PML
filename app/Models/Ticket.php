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
            $agingDuration = $this->aging_start_at->diffInHours($agingEnd);
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
     * Create contract from this ticket data.
     */
    public function createContract(): Contract
    {
        $endDate = $this->getEndDate();
        
        // Determine initial status based on end_date
        $status = 'active';
        if ($endDate && $endDate->isPast()) {
            $status = 'expired';
        }

        $contract = Contract::create([
            'ticket_id' => $this->id,
            'contract_number' => $this->generateContractNumber(),
            'agreement_name' => $this->proposed_document_title,
            'proposed_document_title' => $this->proposed_document_title,
            'document_type' => $this->mapDocumentType(),
            'financial_impact' => null, // Ticket only has boolean has_financial_impact, not income/expenditure direction
            'tat_legal_compliance' => $this->tat_legal_compliance,
            'division_id' => $this->division_id,
            'department_id' => $this->department_id,
            'start_date' => $this->getStartDate(),
            'end_date' => $endDate,
            'is_auto_renewal' => $this->is_auto_renewal,
            'description' => $this->getDescription(),
            'status' => $status,
            'mandatory_documents_path' => $this->mandatory_documents_path,
            'approval_document_path' => $this->approval_document_path,
            'created_by' => $this->created_by,
        ]);

        $this->logActivity("Contract #{$contract->contract_number} dibuat dari ticket ini (Status: {$status})");

        return $contract;
    }

    /**
     * Generate contract number from ticket.
     */
    private function generateContractNumber(): string
    {
        $prefix = 'CTR-'.now()->format('Y-m');
        $lastContract = Contract::where('contract_number', 'like', $prefix.'-%')
            ->orderBy('contract_number', 'desc')
            ->first();

        if ($lastContract) {
            $lastNumber = (int) substr($lastContract->contract_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return $prefix.'-'.$newNumber;
    }

    /**
     * Map ticket document type to contract document type.
     */
    private function mapDocumentType(): string
    {
        return match ($this->document_type) {
            'perjanjian' => 'perjanjian',
            'nda' => 'nda',
            'surat_kuasa' => 'surat_kuasa',
            'pendapat_hukum' => 'pendapat_hukum',
            'surat_pernyataan' => 'surat_pernyataan',
            'surat_lainnya' => 'lainnya',
            default => 'lainnya',
        };
    }

    /**
     * Get start date based on document type.
     */
    private function getStartDate(): ?Carbon
    {
        if (in_array($this->document_type, ['perjanjian', 'nda'])) {
            return $this->agreement_start_date;
        }

        if ($this->document_type === 'surat_kuasa') {
            return $this->kuasa_start_date;
        }

        return now();
    }

    /**
     * Get end date based on document type.
     */
    private function getEndDate(): ?Carbon
    {
        if (in_array($this->document_type, ['perjanjian', 'nda'])) {
            return $this->agreement_end_date;
        }

        if ($this->document_type === 'surat_kuasa') {
            return $this->kuasa_end_date;
        }

        return null;
    }

    /**
     * Get description based on document type.
     */
    private function getDescription(): string
    {
        $description = [];

        if ($this->counterpart_name) {
            $description[] = "Counterpart: {$this->counterpart_name}";
        }

        if ($this->kuasa_pemberi && $this->kuasa_penerima) {
            $description[] = "Pemberi Kuasa: {$this->kuasa_pemberi}";
            $description[] = "Penerima Kuasa: {$this->kuasa_penerima}";
        }

        if ($this->agreement_duration) {
            $description[] = "Jangka Waktu: {$this->agreement_duration}";
        }

        return implode("\n", $description);
    }

    /**
     * Get human-readable aging duration.
     */
    public function getAgingDurationDisplayAttribute(): ?string
    {
        if (! $this->aging_duration) {
            return null;
        }

        $hours = $this->aging_duration;
        $days = floor($hours / 24);
        $remainingHours = $hours % 24;

        if ($days > 0) {
            return "{$days} hari {$remainingHours} jam";
        }

        return "{$hours} jam";
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
            'open' => 'Menunggu Review',
            'on_process' => 'Sedang Diproses',
            'done' => 'Selesai',
            'rejected' => 'Ditolak',
            'closed' => 'Ditutup',
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
    private function logActivity(string $message, ?User $user = null): void
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
