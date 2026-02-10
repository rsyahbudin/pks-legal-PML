<?php

namespace App\Exports;

use App\Models\Ticket;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TicketsExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
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
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter, fn ($q) => $q->where('document_type', $this->typeFilter))
            ->when($this->divisionId, fn ($q) => $q->where('division_id', $this->divisionId))
            ->when($this->startDate, fn ($q) => $q->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn ($q) => $q->whereDate('created_at', '<=', $this->endDate))
            ->orderBy('created_at', 'desc');

        return $query->get()->map(function ($ticket) {
            // Calculate aging based on proper workflow timestamps
            $agingDisplay = '-';
            $totalMinutes = 0;

            // Aging calculation logic based on status and aging_start_at
            if ($ticket->aging_duration && $ticket->aging_duration > 0) {
                // For completed tickets with stored aging_duration (already in minutes)
                $totalMinutes = $ticket->aging_duration;
            } elseif (in_array($ticket->status?->code, ['done', 'closed', 'rejected']) && $ticket->aging_start_at) {
                // For completed tickets: use aging_start_at to aging_end_at (or updated_at as fallback)
                $endTime = $ticket->aging_end_at ?? $ticket->updated_at;
                $totalMinutes = $ticket->aging_start_at->diffInMinutes($endTime);
            } elseif ($ticket->status?->code === 'on_process' && $ticket->aging_start_at) {
                // For in-progress tickets: calculate from aging_start_at to now
                $totalMinutes = $ticket->aging_start_at->diffInMinutes(now());
            }
            // If aging_start_at is not set, aging stays as '-' (ticket hasn't been processed yet)

            // Always show in hours for export
            if ($totalMinutes > 0) {
                $hours = round($totalMinutes / 60, 1); // Convert minutes to hours with 1 decimal
                $agingDisplay = $hours.' hours';
            }

            return [
                'Ticket Number' => $ticket->ticket_number,
                'Document Title' => $ticket->proposed_document_title,
                'Document Type' => $ticket->document_type_label,
                'Division' => $ticket->division?->name ?? '-',
                'Department' => $ticket->department?->name ?? '-',
                'Created By' => $ticket->creator?->name ?? '-',
                'Created Date' => $ticket->created_at->format('d/m/Y H:i'),
                'Last Updated' => $ticket->updated_at->format('d/m/Y H:i'),
                'Status' => $ticket->status_label,
                'Contract Status' => $ticket->contract?->status?->name ?? '-',

                // Aging information
                'Process Started' => $ticket->aging_start_at?->format('d/m/Y H:i') ?? '-',
                'Process Ended' => $ticket->aging_end_at?->format('d/m/Y H:i') ?? '-',
                'Aging' => $agingDisplay,

                // Agreement/Perjanjian fields
                'Counterpart' => $ticket->counterpart_name ?? '-',
                'Agreement Start Date' => $ticket->agreement_start_date?->format('d/m/Y') ?? '-',
                'Agreement Duration (Months)' => $ticket->agreement_duration ?? '-',
                'Auto Renewal' => $ticket->is_auto_renewal ? 'Yes' : 'No',
                'Renewal Period (Months)' => $ticket->renewal_period ?? '-',
                'Renewal Notification (Days)' => $ticket->renewal_notification_days ?? '-',
                'Agreement End Date' => $ticket->agreement_end_date?->format('d/m/Y') ?? '-',
                'Termination Notification (Days)' => $ticket->termination_notification_days ?? '-',

                // Surat Kuasa fields
                'Grantor' => $ticket->kuasa_pemberi ?? '-',
                'Grantee' => $ticket->kuasa_penerima ?? '-',
                'Power of Attorney Start Date' => $ticket->kuasa_start_date?->format('d/m/Y') ?? '-',
                'Power of Attorney End Date' => $ticket->kuasa_end_date?->format('d/m/Y') ?? '-',

                // Common fields
                'Financial Impact' => $ticket->has_financial_impact ? 'Yes' : 'No',
                'Payment Type' => match ($ticket->payment_type) {
                    'pay' => 'Pay',
                    'receive_payment' => 'Receive Payment',
                    default => '-'
                },
                'Recurring' => $ticket->recurring_description ?? '-',
                'TAT Legal Compliance' => $ticket->tat_legal_compliance ? 'Yes' : 'No',

                // Pre-Done Questions (Checklist)
                'All requirements completed?' => $ticket->pre_done_question_1 ? 'Yes' : 'No',
                'Stakeholder agreed?' => $ticket->pre_done_question_2 ? 'Yes' : 'No',
                'Project started?' => $ticket->pre_done_question_3 ? 'Yes' : 'No',
                'PreDone Notes' => $ticket->pre_done_remarks ?? '-',

                'Contract Number' => $ticket->contract?->contract_number ?? '-',
                'Rejection Reason' => $ticket->rejection_reason ?? '-',
                'Termination Reason' => $ticket->contract?->termination_reason ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Ticket Number',
            'Document Title',
            'Document Type',
            'Division',
            'Department',
            'Created By',
            'Created Date',
            'Last Updated',
            'Status',
            'Contract Status',
            'Process Started',
            'Process Ended',
            'Aging',
            'Counterpart',
            'Agreement Start Date',
            'Agreement Duration (Months)',
            'Auto Renewal',
            'Renewal Period (Months)',
            'Renewal Notification (Days)',
            'Agreement End Date',
            'Termination Notification (Days)',
            'Grantor',
            'Grantee',
            'Power of Attorney Start Date',
            'Power of Attorney End Date',
            'Financial Impact',
            'Payment Type',
            'Recurring',
            'TAT Legal Compliance',
            'All requirements completed?',
            'Stakeholder agreed?',
            'Project started?',
            'PreDone Notes',
            'Contract Number',
            'Rejection Reason',
            'Termination Reason',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style header row
        $sheet->getStyle('A1:AJ1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'], // Blue background
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Set header row height
        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // Ticket Number
            'B' => 40,  // Document Title
            'C' => 20,  // Document Type
            'D' => 20,  // Division
            'E' => 20,  // Department
            'F' => 20,  // Created By
            'G' => 18,  // Created Date
            'H' => 18,  // Updated Date
            'I' => 15,  // Status
            'J' => 18,  // Contract Status
            'K' => 18,  // Processing Started
            'L' => 18,  // Processing Ended
            'M' => 15,  // Aging
            'N' => 30,  // Counterpart
            'O' => 20,  // Agreement Start
            'P' => 20,  // Agreement Duration
            'Q' => 15,  // Auto Renewal
            'R' => 20,  // Renewal Period
            'S' => 22,  // Renewal Notification
            'T' => 20,  // Agreement End
            'U' => 25,  // Termination Notification
            'V' => 25,  // Kuasa Pemberi
            'W' => 25,  // Kuasa Penerima
            'X' => 20,  // Kuasa Start
            'Y' => 20,  // Kuasa End
            'Z' => 18,  // Financial Impact
            'AA' => 20, // Payment Type
            'AB' => 30, // Recurring
            'AC' => 18, // TAT Legal
            'AD' => 30, // PreDone Q1
            'AE' => 30, // PreDone Q2
            'AF' => 30, // PreDone Q3
            'AG' => 40, // PreDone Remarks
            'AH' => 20, // Contract Number
            'AI' => 30, // Rejection Reason
            'AJ' => 30, // Termination Reason
        ];
    }

    public function toCsv(): string
    {
        $data = $this->collection();
        $headings = $this->headings();

        $csv = implode(',', $headings)."\n";

        foreach ($data as $row) {
            $values = array_map(function ($value) {
                // Escape quotes and wrap in quotes if contains comma
                $value = str_replace('"', '""', $value ?? '');
                if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
                    return '"'.$value.'"';
                }

                return $value;
            }, $row);
            $csv .= implode(',', $values)."\n";
        }

        return $csv;
    }
}
