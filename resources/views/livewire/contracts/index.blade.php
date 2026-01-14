<?php

use App\Models\Division;
use App\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $divisionFilter = '';

    public string $dateFilter = '';

    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDivisionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function getTicketsProperty(): LengthAwarePaginator
    {
        $user = auth()->user();

        $query = Ticket::with(['division', 'department', 'creator', 'contract'])
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('ticket_number', 'like', "%{$this->search}%")
                    ->orWhere('proposed_document_title', 'like', "%{$this->search}%");
            }))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->divisionFilter, fn ($q) => $q->where('division_id', $this->divisionFilter))
            ->when($this->dateFilter, fn ($q) => $q->whereDate('created_at', $this->dateFilter));

        // Role-based filtering
        if ($user && ! $user->hasAnyRole(['super-admin', 'legal'])) {
            // Regular users: only see their own tickets
            $query->forUser($user->id);
        }
        // Legal & super admin: see all tickets

        return $query->orderBy('created_at', 'desc')->paginate($this->perPage);
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('name')->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Daftar Tickets</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                @if(auth()->user()->hasAnyRole(['super-admin', 'legal']))
                    Manage semua tickets dari seluruh departemen
                @else
                    Tickets yang Anda buat
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            @if(auth()->user()?->hasPermission('tickets.create'))
            <a href="{{ route('tickets.create') }}" wire:navigate>
                <flux:button variant="primary" icon="plus">
                    Buat Ticket Baru
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
                placeholder="Cari nomor ticket..." 
                icon="magnifying-glass"
            />
            
            <flux:input 
                type="date" 
                wire:model.live="dateFilter" 
                icon="calendar-days"
            />
            
            <flux:select wire:model.live="statusFilter">
                <option value="">Semua Status Ticket</option>
                <option value="open">Menunggu Review</option>
                <option value="on_process">Sedang Diproses</option>
                <option value="done">Selesai</option>
                <option value="rejected">Ditolak</option>
                <option value="closed">Ditutup</option>
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
                        <th class="px-4 py-3 text-left">No. Ticket</th>
                        <th class="px-4 py-3 text-left">Judul Dokumen</th>
                        <th class="px-4 py-3 text-left">Divisi</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Dibuat</th>
                        <th class="px-4 py-3 text-center">Updated</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->tickets as $ticket)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="row-{{ $ticket->id }}">
                        <td class="px-4 py-3">
                            <span class="font-medium text-neutral-900 dark:text-white">{{ $ticket->ticket_number }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="max-w-xs">
                                <p class="truncate font-medium text-neutral-900 dark:text-white">{{ $ticket->proposed_document_title }}</p>
                                <p class="text-xs text-neutral-500 dark:text-neutral-400">{{ $ticket->document_type_label }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->division->name }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $color = $ticket->status_color;
                                $badgeClass = match($color) {
                                    'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                                    'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                    'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                    'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                    'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
                                    default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300',
                                };
                                $dotClass = match($color) {
                                    'blue' => 'bg-blue-500',
                                    'yellow' => 'bg-yellow-500',
                                    'green' => 'bg-green-500',
                                    'red' => 'bg-red-500',
                                    'gray' => 'bg-gray-500',
                                    default => 'bg-neutral-500',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClass }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
                                {{ $ticket->status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3 text-center text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->updated_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                <a href="{{ route('tickets.show', $ticket->id) }}" wire:navigate>
                                    <flux:button size="sm" variant="ghost" icon="eye" />
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <flux:icon name="ticket" class="h-12 w-12 text-neutral-300 dark:text-neutral-600" />
                                <p class="mt-4 text-neutral-500 dark:text-neutral-400">Belum ada ticket</p>
                                @if(auth()->user()?->hasPermission('tickets.create'))
                                <a href="{{ route('tickets.create') }}" class="mt-2" wire:navigate>
                                    <flux:button variant="primary" size="sm">Buat Ticket Pertama</flux:button>
                                </a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->tickets->hasPages())
        <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
            {{ $this->tickets->links() }}
        </div>
        @endif
    </div>
</div>
