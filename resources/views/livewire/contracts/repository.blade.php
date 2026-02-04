<?php

use App\Models\Contract;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public string $divisionFilter = '';

    public int $perPage = 10;

    // Folder Link Modal
    public bool $showFolderLinkModal = false;

    public $selectedContract = null;

    public string $folder_link = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDivisionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function getContractsProperty(): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = Contract::with(['department', 'division'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('contract_number', 'like', "%{$this->search}%")
                    ->orWhere('proposed_document_title', 'like', "%{$this->search}%");
            }))
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
            ->when($this->divisionFilter, fn ($q) => $q->where('division_id', $this->divisionFilter));

        // Role-based filtering (Pic sees only their contracts, Legal/Admin sees all)
        if (! $user->hasAnyRole(['super-admin', 'legal'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhere('pic_id', $user->id);
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
    }

    public function editFolderLink($contractId): void
    {
        $contract = Contract::findOrFail($contractId);
        $this->selectedContract = $contract;
        $this->folder_link = $contract->folder_link ?? '';
        $this->showFolderLinkModal = true;
    }

    public function saveFolderLink(): void
    {
        $validated = $this->validate([
            'folder_link' => ['nullable', 'url', 'max:500'],
        ]);

        $oldLink = $this->selectedContract->folder_link;

        $this->selectedContract->update([
            'folder_link' => $this->folder_link ?: null,
        ]);

        // Log activity to the ticket
        if ($this->selectedContract->ticket) {
            $message = $oldLink
                ? 'Folder link diperbarui'
                : 'Folder link ditambahkan';
            $this->selectedContract->ticket->logActivity($message);
        }

        $this->showFolderLinkModal = false;
        $this->dispatch('notify', type: 'success', message: 'Folder link berhasil disimpan');
    }

    public function getDivisionsProperty()
    {
        return \App\Models\Division::active()->orderBy('name')->get();
    }

    public function getContractStatusesProperty()
    {
        return \App\Models\ContractStatus::active()->orderBy('sort_order')->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Repository Kontrak</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Daftar semua kontrak yang telah terbentuk dari tiket.
            </p>
        </div>
        <div class="flex gap-2">
            <!-- Optional: CSV Export if needed later -->
        </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center bg-white p-4 rounded-xl border border-neutral-200 dark:bg-zinc-900 dark:border-neutral-700 shadow-sm">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari Contracts..." />
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="typeFilter" placeholder="Jenis Dokumen">
                <flux:select.option value="">Semua Jenis</flux:select.option>
                <flux:select.option value="perjanjian">Perjanjian</flux:select.option>
                <flux:select.option value="nda">NDA</flux:select.option>
                <flux:select.option value="surat_kuasa">Surat Kuasa</flux:select.option>
            </flux:select>
        </div>
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="statusFilter" placeholder="Filter Status">
                <flux:select.option value="">Semua Status</flux:select.option>
                @foreach($this->contractStatuses as $status)
                    <flux:select.option value="{{ $status->code }}">{{ $status->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if(auth()->user()->hasAnyRole(['super-admin', 'legal']))
        <div class="w-full sm:w-48">
            <flux:select wire:model.live="divisionFilter" placeholder="Filter Division">
                <flux:select.option value="">Semua Division</flux:select.option>
                @foreach($this->divisions as $division)
                    <flux:select.option value="{{ $division->id }}">{{ $division->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @endif
        <div class="w-full sm:w-32">
            <flux:select wire:model.live="perPage">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </flux:select>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-6 py-3 font-medium">Contract #</th>
                        <th class="px-6 py-3 font-medium">Title</th>
                        <th class="px-6 py-3 font-medium">Department</th>
                        <th class="px-6 py-3 font-medium">Start Date</th>
                        <th class="px-6 py-3 font-medium">End Date</th>
                        <th class="px-6 py-3 font-medium text-center">Status</th>
                        <th class="px-6 py-3 font-medium text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->contracts as $contract)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800">
                        <td class="px-6 py-4 font-medium text-neutral-900 dark:text-white whitespace-nowrap">
                            {{ $contract->contract_number }}
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-neutral-900 dark:text-white">{{ $contract->agreement_name }}</div>
                            <div class="text-xs text-neutral-500">{{ $contract->document_type_label }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $contract->department->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            {{ $contract->start_date?->format('d M Y') ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($contract->is_auto_renewal)
                                <div class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400">
                                    <flux:icon name="arrow-path" class="h-4 w-4" />
                                    <span class="text-xs font-medium">Auto Renewal</span>
                                </div>
                                @if($contract->end_date)
                                    <span class="text-xs text-neutral-400 block mt-1">Next: {{ $contract->end_date->format('d M Y') }}</span>
                                @endif
                            @else
                                {{ $contract->end_date?->format('d M Y') ?? '-' }}
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            @php
                                $color = $contract->status?->color ?? 'neutral';
                            @endphp
                            <flux:badge :color="$color" size="sm" inset="top bottom">{{ $contract->status?->name ?? 'Unknown' }}</flux:badge>
                        </td>
                        <td class="px-6 py-4 text-end">
                            <div class="flex items-center justify-end gap-2">
                                @if(auth()->user()->hasAnyRole(['super-admin', 'legal']))
                                <flux:button wire:click="editFolderLink({{ $contract->id }})" icon="link" size="sm" variant="ghost" class="-my-1" title="Folder Link" />
                                @endif
                                <flux:button :href="route('tickets.show', $contract->ticket_id)" icon="eye" size="sm" variant="ghost" class="-my-1" />
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-neutral-500">
                            Tidak ada kontrak yang ditemukan
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Showing <span class="font-medium">{{ $this->contracts->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $this->contracts->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $this->contracts->total() }}</span> results
                </p>
                <div class="w-full sm:w-auto">
                     {{ $this->contracts->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- Folder Link Modal -->
    <flux:modal wire:model="showFolderLinkModal" class="space-y-6">
        <div>
            <flux:heading size="lg">Edit Folder Link</flux:heading>
            <flux:subheading>Contract: {{ $selectedContract?->contract_number }}</flux:subheading>
        </div>

        <flux:field>
            <flux:label>Folder Link (Internal File Sharing)</flux:label>
            <flux:input 
                wire:model="folder_link" 
                type="url"
                placeholder="https://..."
            />
            <flux:description>Masukkan link folder sharing internal (network drive, SharePoint, dll)</flux:description>
            <flux:error name="folder_link" />
        </flux:field>

        <div class="flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showFolderLinkModal', false)">Cancel</flux:button>
            <flux:button wire:click="saveFolderLink" variant="primary">Save</flux:button>
        </div>
    </flux:modal>
</div>
