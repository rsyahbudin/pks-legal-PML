<?php

use App\Models\Contract;
use App\Models\Division;
use App\Models\Partner;
use App\Models\Setting;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Illuminate\Pagination\LengthAwarePaginator;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $colorFilter = '';
    public string $divisionFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedColorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDivisionFilter(): void
    {
        $this->resetPage();
    }

    public function getContractsProperty(): LengthAwarePaginator
    {
        $user = auth()->user();
        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        $query = Contract::with(['partner', 'division', 'pic'])
            ->when($this->search, fn($q) => $q->where(function ($q) {
                $q->where('contract_number', 'like', "%{$this->search}%")
                    ->orWhereHas('partner', fn($q) => $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('company_name', 'like', "%{$this->search}%"));
            }))
            ->when($this->statusFilter, fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->divisionFilter, fn($q) => $q->where('division_id', $this->divisionFilter))
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
            });

        // PIC only sees their own contracts
        if ($user && $user->isPic()) {
            $query->forPic($user->id);
        }

        return $query->orderBy('end_date', 'asc')->paginate(10);
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('name')->get();
    }

    public function deleteContract(int $id): void
    {
        if (!auth()->user()->hasPermission('contracts.delete')) {
            $this->dispatch('notify', type: 'error', message: 'Anda tidak memiliki akses untuk menghapus kontrak.');
            return;
        }

        $contract = Contract::findOrFail($id);
        $contract->delete();

        $this->dispatch('notify', type: 'success', message: 'Kontrak berhasil dihapus.');
    }
}; ?>

<div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Daftar Kontrak</h1>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Kelola semua kontrak PKS</p>
            </div>
            <div class="flex gap-2">
                @if(auth()->user()?->hasPermission('reports.export'))
                <a href="{{ route('contracts.export', ['status' => $statusFilter, 'color' => $colorFilter, 'division' => $divisionFilter]) }}">
                    <flux:button variant="ghost" icon="arrow-down-tray">
                        Export CSV
                    </flux:button>
                </a>
                @endif
                @if(auth()->user()?->hasPermission('contracts.create'))
                <a href="{{ route('contracts.create') }}" wire:navigate>
                    <flux:button variant="primary" icon="plus">
                        Tambah Kontrak
                    </flux:button>
                </a>
                @endif
            </div>
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Cari nomor kontrak atau partner..." 
                    icon="magnifying-glass"
                />
                
                <flux:select wire:model.live="statusFilter">
                    <option value="">Semua Status</option>
                    <option value="draft">Draft</option>
                    <option value="active">Aktif</option>
                    <option value="expired">Expired</option>
                    <option value="terminated">Terminated</option>
                </flux:select>

                <flux:select wire:model.live="colorFilter">
                    <option value="">Semua Kondisi</option>
                    <option value="green">🟢 Aman</option>
                    <option value="yellow">🟡 Mendekati Expired</option>
                    <option value="red">🔴 Kritis / Expired</option>
                </flux:select>

                <flux:select wire:model.live="divisionFilter">
                    <option value="">Semua Divisi</option>
                    @foreach($this->divisions as $division)
                    <option value="{{ $division->id }}">{{ $division->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 text-left">No. Kontrak</th>
                            <th class="px-4 py-3 text-left">Partner</th>
                            <th class="px-4 py-3 text-left">Divisi</th>
                            <th class="px-4 py-3 text-left">PIC</th>
                            <th class="px-4 py-3 text-left">Periode</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse($this->contracts as $contract)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="row-{{ $contract->id }}">
                            <td class="px-4 py-3">
                                <span class="font-medium text-neutral-900 dark:text-white">{{ $contract->contract_number }}</span>
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->partner->display_name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->division->name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->pic_name }}
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->start_date->format('d/m/Y') }} - {{ $contract->end_date->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $color = $contract->status_color;
                                    $badgeClass = match($color) {
                                        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                        default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300',
                                    };
                                    $dotClass = match($color) {
                                        'green' => 'bg-green-500',
                                        'yellow' => 'bg-yellow-500',
                                        'red' => 'bg-red-500',
                                        default => 'bg-neutral-500',
                                    };
                                @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClass }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
                                    @if($contract->status === 'terminated')
                                        Terminated
                                    @elseif($contract->days_remaining > 0)
                                        {{ $contract->days_remaining }} hari
                                    @elseif($contract->days_remaining == 0)
                                        Hari ini
                                    @else
                                        Expired
                                    @endif
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('contracts.show', $contract) }}" wire:navigate>
                                        <flux:button size="sm" variant="ghost" icon="eye" />
                                    </a>
                                    @if(auth()->user()?->hasPermission('contracts.edit'))
                                    <a href="{{ route('contracts.edit', $contract) }}" wire:navigate>
                                        <flux:button size="sm" variant="ghost" icon="pencil" />
                                    </a>
                                    @endif
                                    @if(auth()->user()?->hasPermission('contracts.delete'))
                                    <flux:button 
                                        size="sm" 
                                        variant="ghost" 
                                        icon="trash" 
                                        wire:click="deleteContract({{ $contract->id }})"
                                        wire:confirm="Apakah Anda yakin ingin menghapus kontrak ini?"
                                    />
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <flux:icon name="document-text" class="h-12 w-12 text-neutral-300 dark:text-neutral-600" />
                                    <p class="mt-4 text-neutral-500 dark:text-neutral-400">Belum ada kontrak</p>
                                    @if(auth()->user()?->hasPermission('contracts.create'))
                                    <a href="{{ route('contracts.create') }}" class="mt-2" wire:navigate>
                                        <flux:button variant="primary" size="sm">Tambah Kontrak Pertama</flux:button>
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($this->contracts->hasPages())
            <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
                {{ $this->contracts->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
