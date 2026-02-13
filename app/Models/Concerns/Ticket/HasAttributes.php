<?php

namespace App\Models\Concerns\Ticket;

trait HasAttributes
{
    /**
     * Get human-readable aging duration.
     */
    public function getAgingDurationDisplayAttribute(): ?string
    {
        if (! $this->TCKT_AGING_DURATION) {
            return null;
        }

        // $this->aging_duration is now in MINUTES
        $minutes = $this->TCKT_AGING_DURATION;
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
        // Using relationship 'status' which returns ContractStatus or TicketStatus (LGL_LOV_MASTER) which has LOV_VALUE.
        if ($this->TCKT_AGING_DURATION && $this->TCKT_AGING_DURATION > 0) {
            $totalMinutes = $this->TCKT_AGING_DURATION;
        } elseif (in_array($this->status?->LOV_VALUE, ['done', 'closed', 'rejected']) && $this->TCKT_AGING_START_DT) {
            $endTime = $this->TCKT_AGING_END_DT ?? $this->TCKT_UPDATED_DT;
            $totalMinutes = $this->TCKT_AGING_START_DT->diffInMinutes($endTime);
        } elseif ($this->status?->LOV_VALUE === 'on_process' && $this->TCKT_AGING_START_DT) {
            $totalMinutes = $this->TCKT_AGING_START_DT->diffInMinutes(now());
        }

        if ($totalMinutes <= 0) {
            return '-';
        }

        $days = (int) floor($totalMinutes / 1440);

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
        return $this->status?->LOV_DISPLAY_NAME ?? 'Unknown';
    }

    /**
     * Get status color for badge.
     */
    public function getStatusColorAttribute(): string
    {
        // Color is no longer in DB??
        // LGL_LOV_MASTER dropped color column.
        // It should be handled in Model or View helper based on LOV_VALUE.
        // Return gray as default, or map here.
        $code = $this->status?->LOV_VALUE;

        return match ($code) {
            'open' => 'blue',
            'on_process' => 'yellow',
            'done' => 'green',
            'rejected' => 'red',
            'closed' => 'gray',
            default => 'gray',
        };
    }
}
