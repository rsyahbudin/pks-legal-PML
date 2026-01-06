<?php

namespace App\Exports;

use App\Models\Contract;
use App\Models\Setting;
use Illuminate\Support\Collection;

class ContractsExport
{
    protected ?string $statusFilter;
    protected ?string $colorFilter;
    protected ?int $divisionId;

    public function __construct(
        ?string $statusFilter = null,
        ?string $colorFilter = null,
        ?int $divisionId = null
    ) {
        $this->statusFilter = $statusFilter;
        $this->colorFilter = $colorFilter;
        $this->divisionId = $divisionId;
    }

    public function collection(): Collection
    {
        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        $query = Contract::with(['partner', 'division', 'pic'])
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->divisionId, fn($q) => $q->where('division_id', $this->divisionId))
            ->when($this->colorFilter, function ($q) use ($warningThreshold, $criticalThreshold) {
                return match ($this->colorFilter) {
                    'green' => $q->where('status', 'active')
                        ->whereDate('end_date', '>', now()->addDays($warningThreshold)),
                    'yellow' => $q->where('status', 'active')
                        ->whereDate('end_date', '<=', now()->addDays($warningThreshold))
                        ->whereDate('end_date', '>', now()->addDays($criticalThreshold)),
                    'red' => $q->where(function ($q) use ($criticalThreshold) {
                        $q->where('status', 'expired')
                            ->orWhere(function ($q) use ($criticalThreshold) {
                                $q->where('status', 'active')
                                    ->whereDate('end_date', '<=', now()->addDays($criticalThreshold));
                            });
                    }),
                    default => $q,
                };
            })
            ->orderBy('end_date', 'asc');

        return $query->get()->map(function ($contract) {
            return [
                'No. Kontrak' => $contract->contract_number,
                'Partner' => $contract->partner->display_name,
                'Divisi' => $contract->division->name,
                'PIC' => $contract->pic->name,
                'Tanggal Mulai' => $contract->start_date->format('d/m/Y'),
                'Tanggal Berakhir' => $contract->end_date->format('d/m/Y'),
                'Sisa Hari' => $contract->days_remaining,
                'Status' => ucfirst($contract->status),
                'Kondisi' => match($contract->status_color) {
                    'green' => 'Aman',
                    'yellow' => 'Mendekati Expired',
                    'red' => 'Kritis/Expired',
                    default => '-',
                },
                'Deskripsi' => $contract->description,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No. Kontrak',
            'Partner',
            'Divisi',
            'PIC',
            'Tanggal Mulai',
            'Tanggal Berakhir',
            'Sisa Hari',
            'Status',
            'Kondisi',
            'Deskripsi',
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
