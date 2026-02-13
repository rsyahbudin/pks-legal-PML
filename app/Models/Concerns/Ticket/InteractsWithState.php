<?php

namespace App\Models\Concerns\Ticket;

use App\Models\Contract;
use App\Models\TicketStatus;
use App\Models\User;

trait InteractsWithState
{
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

        $divCode = strtoupper(substr($division->code ?? 'UNK', 0, 3));

        // Format: TIC-DIV-YYMM (e.g., TIC-LEG-2602)
        $year = now()->format('y');
        $month = now()->format('m');
        $prefix = "TIC-{$divCode}-{$year}{$month}";

        // Find last ticket for this division and year
        $lastTicket = static::where('TCKT_NO', 'like', "{$prefix}%")
            ->orderBy('TCKT_NO', 'desc')
            ->first();

        if ($lastTicket) {
            $lastNumber = (int) substr($lastTicket->TCKT_NO, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        // Final format: TIC-DIV-YYMM9999 (e.g., TIC-LEG-26020001)
        return $prefix.$newNumber;
    }

    /**
     * Check if ticket can be reviewed.
     */
    public function canBeReviewed(): bool
    {
        // Check logic
        return $this->status?->LOV_VALUE === 'open';
    }

    /**
     * Move ticket to "on_process" status.
     */
    public function moveToOnProcess(User $reviewer): void
    {
        $this->update([
            'TCKT_STS_ID' => TicketStatus::getIdByCode('on_process'),
            'TCKT_REVIEWED_DT' => now(),
            'TCKT_REVIEWED_BY' => $reviewer->LGL_ROW_ID,
            'TCKT_AGING_START_DT' => now(),
        ]);

        $this->logActivity('Ticket diubah ke status On Process', $reviewer);
    }

    /**
     * Move ticket to "done" status and calculate aging.
     */
    public function moveToDone(?array $preDoneAnswers = null, ?string $remarks = null): void
    {
        // Validasi untuk perjanjian
        // Assuming access documentType using relationship
        if ($this->documentType?->code === 'perjanjian') {
            if (! $preDoneAnswers || count($preDoneAnswers) !== 3) {
                throw new \InvalidArgumentException('Pre-done questions must be answered for Perjanjian');
            }
        }

        $agingEnd = now();
        $agingDuration = null;

        if ($this->TCKT_AGING_START_DT) {
            $agingDuration = $this->TCKT_AGING_START_DT->diffInMinutes($agingEnd);
        }

        $updateData = [
            'TCKT_STS_ID' => TicketStatus::getIdByCode('done'),
            'TCKT_AGING_END_DT' => $agingEnd,
            'TCKT_AGING_DURATION' => $agingDuration,
        ];

        // Jika perjanjian, simpan jawaban dan remarks
        if ($this->documentType?->code === 'perjanjian' && $preDoneAnswers) {
            $updateData['TCKT_POST_QUEST_1'] = $preDoneAnswers[0];
            $updateData['TCKT_POST_QUEST_2'] = $preDoneAnswers[1];
            $updateData['TCKT_POST_QUEST_3'] = $preDoneAnswers[2];
            $updateData['TCKT_POST_RMK'] = $remarks;
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

        if ($this->TCKT_AGING_START_DT) {
            $agingDuration = $this->TCKT_AGING_START_DT->diffInMinutes($agingEnd);
        }

        $this->update([
            'TCKT_STS_ID' => TicketStatus::getIdByCode('rejected'),
            'TCKT_REVIEWED_DT' => now(),
            'TCKT_REVIEWED_BY' => $reviewer->LGL_ROW_ID,
            'TCKT_REJECT_REASON' => $reason,
            'TCKT_AGING_END_DT' => $agingEnd,
            'TCKT_AGING_DURATION' => $agingDuration,
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

        if ($this->TCKT_AGING_START_DT) {
            $agingDuration = $this->TCKT_AGING_START_DT->diffInMinutes($agingEnd);
        }

        $this->update([
            'TCKT_STS_ID' => TicketStatus::getIdByCode('closed'),
            'TCKT_AGING_END_DT' => $agingEnd,
            'TCKT_AGING_DURATION' => $agingDuration,
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
        $endDate = $this->TCKT_AGREE_END_DT;

        if ($this->documentType?->code === 'surat_kuasa') {
            $endDate = $this->TCKT_GRANT_END_DT;
        }

        if ($endDate && $endDate->isPast() && ! $this->TCKT_IS_AUTO_RENEW) {
            $status = 'expired';
        }

        return $this->contract()->create([
            'CONTR_NO' => Contract::generateContractNumber($this->DIV_ID),
            'CONTR_AGREE_NAME' => $this->TCKT_PROP_DOC_TITLE,
            'CONTR_PROP_DOC_TITLE' => $this->TCKT_PROP_DOC_TITLE,
            'CONTR_DOC_TYPE_ID' => $this->TCKT_DOC_TYPE_ID,
            'CONTR_HAS_FIN_IMPACT' => $this->TCKT_HAS_FIN_IMPACT,
            'CONTR_TAT_LGL_COMPLNCE' => $this->TCKT_TAT_LGL_COMPLNCE,
            'CONTR_DIV_ID' => $this->DIV_ID,
            'CONTR_DEPT_ID' => $this->DEPT_ID,
            'CONTR_PIC' => $this->TCKT_CREATED_BY, // Default PIC to ticket creator
            'CONTR_START_DT' => $this->documentType?->code === 'surat_kuasa' ? $this->TCKT_GRANT_START_DT : $this->TCKT_AGREE_START_DT,
            'CONTR_END_DT' => $endDate,
            'CONTR_IS_AUTO_RENEW' => $this->TCKT_IS_AUTO_RENEW,
            'CONTR_DESC' => $this->TCKT_COUNTERPART_NAME
                ? "Pihak Lawan: {$this->TCKT_COUNTERPART_NAME}"
                : ($this->documentType?->code === 'surat_kuasa' ? "Pemberi Kuasa: {$this->TCKT_GRANTOR}, Penerima: {$this->TCKT_GRANTEE}" : null),
            'CONTR_STS_ID' => \App\Models\ContractStatus::getIdByCode($status),
            'CONTR_DOC_REQUIRED_PATH' => $this->TCKT_DOC_REQUIRED_PATH,
            'CONTR_DOC_APPROVAL_PATH' => $this->TCKT_DOC_APPROVAL_PATH,
            'CONTR_CREATED_BY' => auth()->id() ?? $this->TCKT_REVIEWED_BY ?? $this->TCKT_CREATED_BY,
        ]);
    }

    /**
     * Log activity for this ticket.
     */
    public function logActivity(string $message, ?User $user = null): void
    {
        $this->activityLogs()->create([
            'LOG_CAUSER_ID' => $user?->LGL_ROW_ID ?? auth()->id(),
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'custom', // or status_change
            'LOG_DESC' => $message,
            'LOG_PROPERTIES' => [
                'ticket_number' => $this->TCKT_NO,
                'status' => $this->status?->LOV_VALUE,
            ],
            'LOG_NAME' => 'ticket_activity',
        ]);
    }
}
