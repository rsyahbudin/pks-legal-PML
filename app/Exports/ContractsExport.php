<?php

namespace App\Exports;

use App\Models\Contract;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContractsExport implements FromCollection, WithColumnWidths, WithHeadings, WithStyles
{
    protected ?string $statusFilter;

    protected ?string $typeFilter;

    protected ?int $divisionId;

    public function __construct(
        ?string $statusFilter = null,
        ?string $typeFilter = null,
        ?int $divisionId = null
    ) {
        $this->statusFilter = $statusFilter;
        $this->typeFilter = $typeFilter;
        $this->divisionId = $divisionId;
    }

    public function collection(): Collection
    {
        $query = Contract::with(['division', 'department', 'ticket', 'documentType', 'status'])
            ->when($this->statusFilter, function ($q) {
                if ($this->statusFilter === 'active') {
                    $q->active();
                } elseif ($this->statusFilter === 'expired') {
                    $q->expired();
                } else {
                    $q->whereHas('status', fn ($sq) => $sq->where('code', $this->statusFilter));
                }
            })
            ->when($this->typeFilter, fn ($q) => $q->whereHas('documentType', fn ($sq) => $sq->where('code', $this->typeFilter)))
            ->when($this->divisionId, fn ($q) => $q->where('division_id', $this->divisionId))
            ->orderBy('created_at', 'desc');

        return $query->get()->map(function ($contract) {
            return [
                'Contract Number' => $contract->contract_number ?? '-',
                'Counterpart' => $contract->ticket?->counterpart_name ?? '-',
                'Division' => $contract->division?->name ?? '-',
                'Document Type' => $contract->documentType?->name ?? '-',
                'Title' => $contract->agreement_name ?? $contract->proposed_document_title ?? '-',
                'Start Date' => $contract->start_date?->format('d/m/Y') ?? '-',
                'End Date' => $contract->end_date?->format('d/m/Y') ?? '-',
                'Status' => $contract->status?->name ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Contract Number',
            'Counterpart',
            'Division',
            'Document Type',
            'Title',
            'Start Date',
            'End Date',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(25);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22, // No. Kontrak
            'B' => 30, // Counterpart
            'C' => 20, // Divisi
            'D' => 20, // Jenis Dokumen
            'E' => 40, // Judul
            'F' => 18, // Tanggal Mulai
            'G' => 18, // Tanggal Berakhir
            'H' => 18, // Status
        ];
    }
}
