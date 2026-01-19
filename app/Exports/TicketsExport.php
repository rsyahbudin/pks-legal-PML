<?php

namespace App\Exports;

use App\Models\Ticket;
use Illuminate\Support\Collection;

class TicketsExport
{
    protected ?string $statusFilter;
    protected ?string $typeFilter;
    protected ?int $divisionId;
    protected ?string $startDate;
    protected ?string $endDate;

    public function __construct(
        ?string $statusFilter = null,
        ?string $typeFilter = null,
        ?int $divisionId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $this->statusFilter = $statusFilter;
        $this->typeFilter = $typeFilter;
        $this->divisionId = $divisionId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        $query = Ticket::with(['division', 'department', 'creator', 'contract'])
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn($q) => $q->where('document_type', $this->typeFilter))
            ->when($this->divisionId, fn($q) => $q->where('division_id', $this->divisionId))
            ->when($this->startDate, fn($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->orderBy('created_at', 'desc');

        return $query->get()->map(function ($ticket) {
            // Calculate aging based on proper workflow timestamps
            $agingDisplay = '-';
            $totalMinutes = 0;
            
            // Aging calculation logic based on status and aging_start_at
            if ($ticket->aging_duration && $ticket->aging_duration > 0) {
                // For completed tickets with stored aging_duration (already in minutes)
                $totalMinutes = $ticket->aging_duration;
            } elseif (in_array($ticket->status, ['done', 'closed', 'rejected']) && $ticket->aging_start_at) {
                // For completed tickets: use aging_start_at to aging_end_at (or updated_at as fallback)
                $endTime = $ticket->aging_end_at ?? $ticket->updated_at;
                $totalMinutes = $ticket->aging_start_at->diffInMinutes($endTime);
            } elseif ($ticket->status === 'on_process' && $ticket->aging_start_at) {
                // For in-progress tickets: calculate from aging_start_at to now
                $totalMinutes = $ticket->aging_start_at->diffInMinutes(now());
            }
            // If aging_start_at is not set, aging stays as '-' (ticket hasn't been processed yet)
            
            // Smart unit selection for better readability
            if ($totalMinutes > 0) {
                if ($totalMinutes >= 1440) {
                    // Show in days if >= 24 hours
                    $days = (int) round($totalMinutes / 1440);
                    $agingDisplay = $days . ' hari';
                } elseif ($totalMinutes >= 60) {
                    // Show in hours if >= 1 hour
                    $hours = (int) round($totalMinutes / 60);
                    $agingDisplay = $hours . ' jam';
                } else {
                    // Show in minutes if < 1 hour, minimum 1 minute
                    $minutes = max(1, (int) round($totalMinutes));
                    $agingDisplay = $minutes . ' menit';
                }
            }
            
            return [
                'No. Ticket' => $ticket->ticket_number,
                'Judul Dokumen' => $ticket->proposed_document_title,
                'Jenis Dokumen' => $ticket->document_type_label,
                'Divisi' => $ticket->division?->name ?? '-',
                'Departemen' => $ticket->department?->name ?? '-',
                'Dibuat Oleh' => $ticket->creator?->name ?? '-',
                'Tanggal Dibuat' => $ticket->created_at->format('d/m/Y H:i'),
                'Terakhir Diupdate' => $ticket->updated_at->format('d/m/Y H:i'),
                'Status' => $ticket->status_label,
                
                // Aging information
                'Mulai Diproses' => $ticket->aging_start_at?->format('d/m/Y H:i') ?? '-',
                'Selesai Diproses' => $ticket->aging_end_at?->format('d/m/Y H:i') ?? '-',
                'Aging' => $agingDisplay,
                
                // Agreement/Perjanjian fields
                'Counterpart' => $ticket->counterpart_name ?? '-',
                'Tanggal Mulai Perjanjian' => $ticket->agreement_start_date?->format('d/m/Y') ?? '-',
                'Durasi Perjanjian (Bulan)' => $ticket->agreement_duration ?? '-',
                'Auto Renewal' => $ticket->is_auto_renewal ? 'Ya' : 'Tidak',
                'Periode Renewal (Bulan)' => $ticket->renewal_period ?? '-',
                'Notifikasi Renewal (Hari)' => $ticket->renewal_notification_days ?? '-',
                'Tanggal Akhir Perjanjian' => $ticket->agreement_end_date?->format('d/m/Y') ?? '-',
                'Notifikasi Terminasi (Hari)' => $ticket->termination_notification_days ?? '-',
                
                // Surat Kuasa fields
                'Pemberi Kuasa' => $ticket->kuasa_pemberi ?? '-',
                'Penerima Kuasa' => $ticket->kuasa_penerima ?? '-',
                'Tanggal Mulai Kuasa' => $ticket->kuasa_start_date?->format('d/m/Y') ?? '-',
                'Tanggal Akhir Kuasa' => $ticket->kuasa_end_date?->format('d/m/Y') ?? '-',
                
                // Common fields
                'Dampak Finansial' => $ticket->has_financial_impact ? 'Ya' : 'Tidak',
                'TAT Legal Compliance' => $ticket->tat_legal_compliance ? 'Ya' : 'Tidak',
                'No. Kontrak' => $ticket->contract?->contract_number ?? '-',
                'Alasan Penolakan' => $ticket->rejection_reason ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No. Ticket',
            'Judul Dokumen',
            'Jenis Dokumen',
            'Divisi',
            'Departemen',
            'Dibuat Oleh',
            'Tanggal Dibuat',
            'Terakhir Diupdate',
            'Status',
            'Mulai Diproses',
            'Selesai Diproses',
            'Aging',
            'Counterpart',
            'Tanggal Mulai Perjanjian',
            'Durasi Perjanjian (Bulan)',
            'Auto Renewal',
            'Periode Renewal (Bulan)',
            'Notifikasi Renewal (Hari)',
            'Tanggal Akhir Perjanjian',
            'Notifikasi Terminasi (Hari)',
            'Pemberi Kuasa',
            'Penerima Kuasa',
            'Tanggal Mulai Kuasa',
            'Tanggal Akhir Kuasa',
            'Dampak Finansial',
            'TAT Legal Compliance',
            'No. Kontrak',
            'Alasan Penolakan',
        ];
    }

    public function toCsv(): string
    {
        $data = $this->collection();
        $headings = $this->headings();

        $csv = implode(',', $headings) . "\n";

        foreach ($data as $row) {
            $values = array_map(function ($value) {
                // Escape quotes and wrap in quotes if contains comma
                $value = str_replace('"', '""', $value ?? '');
                if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                    return '"' . $value . '"';
                }
                return $value;
            }, $row);
            $csv .= implode(',', $values) . "\n";
        }

        return $csv;
    }
}
