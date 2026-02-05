<?php

namespace App\Models;

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
        'payment_type',
        'recurring_description',
        'proposed_document_title',
        'draft_document_path',
        'document_type_id',
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
        'status_id',
        'reviewed_at',
        'reviewed_by',
        'aging_start_at',
        'aging_end_at',
        'aging_duration',
        'rejection_reason',
        'pre_done_question_1',
        'pre_done_question_2',
        'pre_done_question_3',
        'pre_done_remarks',
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
            'pre_done_question_1' => 'boolean',
            'pre_done_question_2' => 'boolean',
            'pre_done_question_3' => 'boolean',
        ];
    }

    /**
     * Boot method to auto-generate ticket number and adjust created_at.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            // Auto-generate ticket number
            if (! $ticket->ticket_number) {
                $ticket->ticket_number = static::generateTicketNumber($ticket->division_id);
            }

            // Adjust created_at if ticket created after cutoff time
            $now = now();
            $cutoffTime = Setting::get('ticket_cutoff_time', '17:00');
            $cutoffHour = (int) substr($cutoffTime, 0, 2); // Extract hour from HH:mm

            if ($now->hour >= $cutoffHour) {
                // Add 1 day to the date, keep the same time
                $ticket->created_at = $now->addDay();
            }
        });
    }

    /**
     * Generate unique ticket number: TKT-YYYY-MM-XXXX
     */
    /**
     * Generate unique ticket number: TIC-{DIV_CODE}-{YYMM}{9999}
     * Resets sequence yearly per division
     */
    public static function generateTicketNumber(int $divisionId): string
    {
        $division = \App\Models\Division::find($divisionId);

        if (! $division) {
            throw new \Exception("Division not found for ID: {$divisionId}");
        }

        // Truncate division code to max 3 characters
        $divCode = strtoupper(substr($division->code, 0, 3));

        // Format: TIC-DIV-YYMM (e.g., TIC-LEG-2602)
        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = "TIC-{$divCode}-{$year}{$month}";

        // Find last ticket for this division and year
        $lastTicket = static::where('ticket_number', 'like', "TIC-{$divCode}-{$year}%")
            ->orderBy('ticket_number', 'desc')
            ->first();

        if ($lastTicket) {
            $lastNumber = (int) substr($lastTicket->ticket_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        // Final format: TIC-DIV-YYMM9999 (e.g., TIC-LEG-26020001)
        return $prefix.$newNumber;
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
     * Get the status for this ticket.
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    /**
     * Get the document type for this ticket.
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
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
        return $this->status?->code === 'open';
    }

    /**
     * Move ticket to "on_process" status.
     */
    public function moveToOnProcess(User $reviewer): void
    {
        $this->update([
            'status_id' => TicketStatus::getIdByCode('on_process'),
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'aging_start_at' => now(),
        ]);

        $this->logActivity('Ticket diubah ke status On Process', $reviewer);
    }

    /**
     * Move ticket to "done" status and calculate aging.
     */
    public function moveToDone(?array $preDoneAnswers = null, ?string $remarks = null): void
    {
        // Validasi untuk perjanjian
        if ($this->documentType?->code === 'perjanjian') {
            if (! $preDoneAnswers || count($preDoneAnswers) !== 3) {
                throw new \InvalidArgumentException('Pre-done questions must be answered for Perjanjian');
            }
        }

        $agingEnd = now();
        $agingDuration = null;

        if ($this->aging_start_at) {
            $agingDuration = $this->aging_start_at->diffInMinutes($agingEnd);
        }

        $updateData = [
            'status_id' => TicketStatus::getIdByCode('done'),
            'aging_end_at' => $agingEnd,
            'aging_duration' => $agingDuration,
        ];

        // Jika perjanjian, simpan jawaban dan remarks
        if ($this->documentType?->code === 'perjanjian' && $preDoneAnswers) {
            $updateData['pre_done_question_1'] = $preDoneAnswers[0];
            $updateData['pre_done_question_2'] = $preDoneAnswers[1];
            $updateData['pre_done_question_3'] = $preDoneAnswers[2];
            $updateData['pre_done_remarks'] = $remarks;
        }

        $this->update($updateData);

        $this->logActivity('Ticket diselesaikan (Done)');
    }

    /**
     * Reject ticket with reason.
     */
    public function reject(string $reason, User $reviewer): void
    {
        $agingEnd = now();
        $agingDuration = null;

        if ($this->aging_start_at) {
            $agingDuration = $this->aging_start_at->diffInMinutes($agingEnd);
        }

        $this->update([
            'status_id' => TicketStatus::getIdByCode('rejected'),
            'reviewed_at' => now(),
            'reviewed_by' => $reviewer->id,
            'rejection_reason' => $reason,
            'aging_end_at' => $agingEnd,
            'aging_duration' => $agingDuration,
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
            'status_id' => TicketStatus::getIdByCode('closed'),
            'aging_end_at' => $agingEnd,
            'aging_duration' => $agingDuration,
        ]);

        $this->logActivity('Ticket ditutup (tidak memerlukan contract)');
    }

    /**
     * Create contract from this ticket.
     */
    public function createContract(): Contract
    {
        // Calculate contract status based on end date
        $status = 'active';
        $endDate = $this->agreement_end_date;

        // Handle Surat Kuasa using kuasa_end_date
        if ($this->documentType?->code === 'surat_kuasa') {
            $endDate = $this->kuasa_end_date;
        }

        if ($endDate && $endDate->isPast() && ! $this->is_auto_renewal) {
            $status = 'expired';
        }

        return $this->contract()->create([
            'contract_number' => Contract::generateContractNumber($this->division_id),
            'agreement_name' => $this->proposed_document_title,
            'proposed_document_title' => $this->proposed_document_title,
            'document_type_id' => $this->document_type_id,
            'has_financial_impact' => $this->has_financial_impact,
            'tat_legal_compliance' => $this->tat_legal_compliance,
            'division_id' => $this->division_id,
            'department_id' => $this->department_id,
            'pic_id' => $this->created_by, // Default PIC to ticket creator
            'pic_name' => $this->creator->name ?? 'Unknown',
            'pic_email' => $this->creator->email ?? null,
            'start_date' => $this->documentType?->code === 'surat_kuasa' ? $this->kuasa_start_date : $this->agreement_start_date,
            'end_date' => $endDate,
            'is_auto_renewal' => $this->is_auto_renewal,
            'description' => $this->counterpart_name
                ? "Pihak Lawan: {$this->counterpart_name}"
                : ($this->documentType?->code === 'surat_kuasa' ? "Pemberi Kuasa: {$this->kuasa_pemberi}, Penerima: {$this->kuasa_penerima}" : null),
            'status_id' => \App\Models\ContractStatus::getIdByCode($status),
            'mandatory_documents_path' => $this->mandatory_documents_path,
            'approval_document_path' => $this->approval_document_path,
            'created_by' => auth()->id() ?? $this->reviewed_by ?? $this->created_by,
        ]);
    }

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
        if ($days > 0) {
            $parts[] = "{$days} hari";
        }
        if ($hours > 0) {
            $parts[] = "{$hours} jam";
        }
        if ($mins > 0 || empty($parts)) {
            $parts[] = "{$mins} menit";
        }

        return implode(' ', $parts);
    }

    /**
     * Get smart aging display based on ticket status and timestamps.
     */
    public function getAgingDisplayAttribute(): string
    {
        $totalMinutes = 0;

        // Calculate aging based on status and available timestamps
        if ($this->aging_duration && $this->aging_duration > 0) {
            // For completed tickets with stored aging_duration
            $totalMinutes = $this->aging_duration;
        } elseif (in_array($this->status?->code, ['done', 'closed', 'rejected']) && $this->aging_start_at) {
            // For completed tickets: use aging_start_at to aging_end_at (or updated_at as fallback)
            $endTime = $this->aging_end_at ?? $this->updated_at;
            $totalMinutes = $this->aging_start_at->diffInMinutes($endTime);
        } elseif ($this->status?->code === 'on_process' && $this->aging_start_at) {
            // For in-progress tickets: calculate from aging_start_at to now
            $totalMinutes = $this->aging_start_at->diffInMinutes(now());
        }

        // Return '-' if no aging (ticket not yet processed)
        if ($totalMinutes <= 0) {
            return '-';
        }

        // Always show in days for web display (rounded down)
        $days = (int) floor($totalMinutes / 1440); // Convert minutes to days, rounded down

        return $days.' days';
    }

    /**
     * Get document type label.
     */
    public function getDocumentTypeLabelAttribute(): string
    {
        return $this->documentType?->name ?? 'Unknown';
    }

    /**
     * Get status label (human-readable).
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status?->name ?? 'Unknown';
    }

    /**
     * Get status color for badge.
     */
    public function getStatusColorAttribute(): string
    {
        return $this->status?->color ?? 'gray';
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
