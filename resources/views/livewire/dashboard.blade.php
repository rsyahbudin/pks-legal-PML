<?php

use App\Models\Contract;
use App\Models\Setting;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    // Filter properties
    public $agingFilter = 'all';

    public function isLegalUser(): bool
    {
        $user = auth()->user();

        return $user && $user->hasAnyRole(['super-admin', 'legal']);
    }

    // TICKET STATISTICS (for Legal Dashboard)
    public function getTicketStatisticsProperty(): array
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission('dashboard.tickets.view')) {
            return [];
        }

        return [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'on_process' => Ticket::where('status', 'on_process')->count(),
            'done' => Ticket::where('status', 'done')->count(),
            'rejected' => Ticket::where('status', 'rejected')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
        ];
    }

    public function getAgingOverviewProperty(): array
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission('dashboard.aging.view')) {
            return [];
        }

        $onProcessTickets = Ticket::where('status', 'on_process')
            ->whereNotNull('aging_start_at')
            ->get();

        $doneTickets = Ticket::where('status', 'done')
            ->whereNotNull('aging_duration')
            ->get();

        $avgOngoing = $onProcessTickets->avg(function ($ticket) {
            return $ticket->aging_start_at
                ? now()->diffInMinutes($ticket->aging_start_at)
                : 0;
        });

        $avgCompleted = $doneTickets->avg('aging_duration') ?? 0;

        $longest = $onProcessTickets->sortByDesc(function ($ticket) {
            return $ticket->aging_start_at
                ? now()->diffInMinutes($ticket->aging_start_at)
                : 0;
        })->first();

        return [
            'avg_ongoing_minutes' => round($avgOngoing),
            'avg_completed_minutes' => round($avgCompleted),
            'longest_ticket' => $longest,
        ];
    }

    public function getTicketsNeedingAttentionProperty(): Collection
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission('dashboard.tickets.view')) {
            return collect();
        }

        $query = Ticket::with(['creator', 'division', 'contract'])
            ->whereIn('status', ['open', 'on_process']);

        // Fetch enough records to filter (since we can't easily do calculated aging in SQL cross-db)
        // Or better: Use collection filtering after fetching all candidate tickets (limit 100?)
        // Since this is "Needing Attention", usually volume isn't huge.
        
        $tickets = $query->orderBy('created_at', 'asc')->get();

        // Filter by aging
        if ($this->agingFilter !== 'all') {
            $tickets = $tickets->filter(function ($ticket) {
                $start = $ticket->aging_start_at ?? $ticket->created_at;
                $days = now()->diffInDays($start);

                return match ($this->agingFilter) {
                    'less_3' => $days < 3,
                    '3_to_7' => $days >= 3 && $days <= 7,
                    'more_7' => $days > 7,
                    default => true,
                };
            });
        }

        return $tickets->take(5);
    }

    // USER TICKET STATISTICS (for Regular User Dashboard)
    public function getMyTicketStatisticsProperty(): array
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission('dashboard.my-tickets.view')) {
            return [];
        }

        return [
            'total' => Ticket::where('created_by', $user->id)->count(),
            'open' => Ticket::where('created_by', $user->id)->where('status', 'open')->count(),
            'on_process' => Ticket::where('created_by', $user->id)->where('status', 'on_process')->count(),
            'done' => Ticket::where('created_by', $user->id)->where('status', 'done')->count(),
            'rejected' => Ticket::where('created_by', $user->id)->where('status', 'rejected')->count(),
            'closed' => Ticket::where('created_by', $user->id)->where('status', 'closed')->count(),
        ];
    }

    public function getMyRecentTicketsProperty(): Collection
    {
        $user = auth()->user();
        if (! $user || ! $user->hasPermission('dashboard.my-tickets.view')) {
            return collect();
        }

        return Ticket::with(['division', 'department', 'contract'])
            ->where('created_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    // CONTRACT STATISTICS (existing, for Legal Dashboard)
    public function getStatisticsProperty(): array
    {
        $user = auth()->user();
        if (! $user) {
            return ['total' => 0, 'active' => 0, 'expiring_soon' => 0, 'critical' => 0, 'expired' => 0];
        }

        $query = Contract::query();

        $query = Contract::query();

        if (method_exists($user, 'isPic') && $user->isPic()) {
            $query->forPic($user->id);
        }

        $total = (clone $query)->count();
        $active = (clone $query)->active()->count();
        $expired = (clone $query)->expired()->count();
        $terminated = (clone $query)->where('status', 'terminated')->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'terminated' => $terminated,
        ];
    }

    public function getRecentContractsProperty(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        $query = Contract::with(['division', 'pic', 'ticket'])
            ->orderBy('end_date', 'asc');

        if (method_exists($user, 'isPic') && $user->isPic()) {
            $query->forPic($user->id);
        }

        return $query->limit(5)->get();
    }

    public function getExpiringContractsProperty(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return collect();
        }

        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);

        $query = Contract::with(['division', 'pic', 'ticket'])
            ->where('status', 'active')
            ->whereDate('end_date', '<=', now()->addDays($warningThreshold))
            ->orderBy('end_date', 'asc');

        if (method_exists($user, 'isPic') && $user->isPic()) {
            $query->forPic($user->id);
        }

        return $query->limit(5)->get();
    }
}; ?>

<div class="space-y-6">
    @if(auth()->user()->hasPermission('dashboard.tickets.view'))
    {{-- LEGAL DASHBOARD --}}
    
    <!-- Ticket Statistics Cards -->
    <div>
        <h2 class="mb-4 text-xl font-bold text-neutral-900 dark:text-white">Ticket Overview</h2>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Total</p>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $this->ticketStatistics['total'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <p class="text-xs text-blue-700 dark:text-blue-400">Open</p>
                <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ $this->ticketStatistics['open'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950/30">
                <p class="text-xs text-yellow-700 dark:text-yellow-400">On Process</p>
                <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ $this->ticketStatistics['on_process'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/30">
                <p class="text-xs text-green-700 dark:text-green-400">Done</p>
                <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $this->ticketStatistics['done'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                <p class="text-xs text-red-700 dark:text-red-400">Rejected</p>
                <p class="text-2xl font-bold text-red-700 dark:text-red-400">{{ $this->ticketStatistics['rejected'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-xs text-neutral-600 dark:text-neutral-400">Closed</p>
                <p class="text-2xl font-bold text-neutral-600 dark:text-neutral-400">{{ $this->ticketStatistics['closed'] ?? 0 }}</p>
            </div>
        </div>
    </div>

    <!-- Aging Overview -->
    @if($this->agingOverview['avg_ongoing_minutes'] > 0 || $this->agingOverview['avg_completed_minutes'] > 0)
    <div class="rounded-xl border border-neutral-200 bg-gradient-to-r from-blue-50 to-purple-50 p-6 dark:border-neutral-700 dark:from-blue-950/30 dark:to-purple-950/30">
        <h3 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Aging Overview</h3>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">Avg Ongoing (On Process)</p>
                <p class="text-xl font-bold text-blue-700 dark:text-blue-400">
                    {{ floor($this->agingOverview['avg_ongoing_minutes'] / 1440) }} hari {{ floor(($this->agingOverview['avg_ongoing_minutes'] % 1440) / 60) }} jam
                </p>
            </div>
            <div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">Avg Completed</p>
                <p class="text-xl font-bold text-green-700 dark:text-green-400">
                    {{ floor($this->agingOverview['avg_completed_minutes'] / 1440) }} hari {{ floor(($this->agingOverview['avg_completed_minutes'] % 1440) / 60) }} jam
                </p>
            </div>
            @if($this->agingOverview['longest_ticket'])
            <div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">Longest Ongoing</p>
                <p class="text-lg font-bold text-red-700 dark:text-red-400">
                    {{ $this->agingOverview['longest_ticket']->ticket_number }}
                </p>
                <p class="text-xs text-neutral-500 dark:text-neutral-400">
                    {{ floor(now()->diffInMinutes($this->agingOverview['longest_ticket']->aging_start_at) / 1440) }} hari
                </p>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Tickets Needing Attention -->
    @if($this->ticketsNeedingAttention->isNotEmpty() || $this->agingFilter !== 'all')
    <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="border-b border-neutral-200 p-4 dark:border-neutral-700">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-neutral-900 dark:text-white">Tickets Needing Attention</h3>
                
                <!-- Filter Dropdown -->
                <div class="flex items-center gap-2">
                    <label class="text-sm text-neutral-600 dark:text-neutral-400">Filter Aging:</label>
                    <select wire:model.live="agingFilter" class="rounded-lg border-neutral-300 text-sm dark:border-neutral-600 dark:bg-zinc-800">
                        <option value="all">Semua</option>
                        <option value="less_3">< 3 Hari</option>
                        <option value="3_to_7">3 - 7 Hari</option>
                        <option value="more_7">> 7 Hari</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Ticket #</th>
                        <th class="px-4 py-3 text-left">Title</th>
                        <th class="px-4 py-3 text-left">Created By</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aging</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @foreach($this->ticketsNeedingAttention as $ticket)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800">
                        <td class="px-4 py-3 text-sm font-medium">
                            <a href="{{ route('tickets.show', $ticket->id) }}" class="text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                                {{ $ticket->ticket_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ Str::limit($ticket->proposed_document_title, 40) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                            {{ $ticket->creator->name }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $statusConfig = [
                                    'open' => ['class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', 'label' => 'Open'],
                                    'on_process' => ['class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', 'label' => 'On Process'],
                                ];
                                $config = $statusConfig[$ticket->status] ?? ['class' => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300', 'label' => ucfirst($ticket->status)];
                            @endphp
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $config['class'] }}">
                                {{ $config['label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center text-sm font-medium text-neutral-900 dark:text-white">
                            @php
                                $start = $ticket->aging_start_at ?? $ticket->created_at;
                                $diff = now()->diff($start);
                                $days = $diff->days;
                                $hours = $diff->h;
                            @endphp
                            {{ $days }}d {{ $hours }}h
                        </td>
                    </tr>
                    @endforeach
                    @if($this->ticketsNeedingAttention->isEmpty())
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-neutral-500 dark:text-neutral-400">
                            Tidak ada ticket yang sesuai dengan filter.
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <h2 class="text-xl font-bold text-neutral-900 dark:text-white">Contract Overview</h2>

    @else
    {{-- USER DASHBOARD --}}
    <div>
        <h2 class="mb-4 text-xl font-bold text-neutral-900 dark:text-white">My Tickets</h2>
        
        <!-- My Ticket Statistics -->
        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
                <p class="text-xs text-neutral-500 dark:text-neutral-400">Total</p>
                <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $this->myTicketStatistics['total'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
                <p class="text-xs text-blue-700 dark:text-blue-400">Open</p>
                <p class="text-2xl font-bold text-blue-700 dark:text-blue-400">{{ $this->myTicketStatistics['open'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900 dark:bg-yellow-950/30">
                <p class="text-xs text-yellow-700 dark:text-yellow-400">On Process</p>
                <p class="text-2xl font-bold text-yellow-700 dark:text-yellow-400">{{ $this->myTicketStatistics['on_process'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/30">
                <p class="text-xs text-green-700 dark:text-green-400">Done</p>
                <p class="text-2xl font-bold text-green-700 dark:text-green-400">{{ $this->myTicketStatistics['done'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                <p class="text-xs text-red-700 dark:text-red-400">Rejected</p>
                <p class="text-2xl font-bold text-red-700 dark:text-red-400">{{ $this->myTicketStatistics['rejected'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <p class="text-xs text-neutral-600 dark:text-neutral-400">Closed</p>
                <p class="text-2xl font-bold text-neutral-600 dark:text-neutral-400">{{ $this->myTicketStatistics['closed'] ?? 0 }}</p>
            </div>
        </div>

        <!-- My Recent Tickets -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <h3 class="text-lg font-semibold text-neutral-900 dark:text-white">My Recent Tickets</h3>
                <a href="{{ route('tickets.index') }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                    View All →
                </a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 text-left">Ticket #</th>
                            <th class="px-4 py-3 text-left">Title</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-left">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse($this->myRecentTickets as $ticket)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800">
                            <td class="px-4 py-3 text-sm font-medium">
                                <a href="{{ route('tickets.show', $ticket->id) }}" class="text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                                    {{ $ticket->ticket_number }}
                                </a>
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ Str::limit($ticket->proposed_document_title, 50) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $statusConfig = [
                                        'open' => ['class' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', 'label' => 'Open'],
                                        'on_process' => ['class' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', 'label' => 'On Process'],
                                        'done' => ['class' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', 'label' => 'Done'],
                                        'rejected' => ['class' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', 'label' => 'Rejected'],
                                        'closed' => ['class' => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300', 'label' => 'Closed'],
                                    ];
                                    $config = $statusConfig[$ticket->status] ?? ['class' => 'bg-neutral-100 text-neutral-800', 'label' => ucfirst($ticket->status)];
                                @endphp
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $config['class'] }}">
                                    {{ $config['label'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $ticket->created_at->format('d M Y') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-neutral-500 dark:text-neutral-400">
                                Belum ada ticket. <a href="{{ route('tickets.create') }}" class="text-blue-600 hover:underline dark:text-blue-400" wire:navigate>Buat ticket pertama</a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <h2 class="mt-6 text-xl font-bold text-neutral-900 dark:text-white">Contract Overview</h2>
    @endif

    <!-- Contract Statistics Cards (shown for both legal and regular users) -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Contracts -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                    <flux:icon name="document-text" class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Total Kontrak</p>
                    <p class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $this->statistics['total'] }}</p>
                </div>
            </div>
        </div>

        <!-- Active Contracts -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                    <flux:icon name="check-circle" class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Aktif</p>
                    <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $this->statistics['active'] }}</p>
                </div>
            </div>
        </div>

        <!-- Expired -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="x-circle" class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Expired</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->statistics['expired'] }}</p>
                </div>
            </div>
        </div>

        <!-- Terminated -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800">
                    <flux:icon name="stop" class="h-6 w-6 text-neutral-600 dark:text-neutral-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Terminated</p>
                    <p class="text-2xl font-bold text-neutral-600 dark:text-neutral-400">{{ $this->statistics['terminated'] }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Expiring Soon Alert -->
    @if($this->expiringContracts->isNotEmpty())
    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 dark:border-yellow-900/50 dark:bg-yellow-900/20">
        <div class="flex items-start gap-3">
            <flux:icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 text-yellow-600 dark:text-yellow-400" />
            <div class="flex-1">
                <h3 class="font-semibold text-yellow-800 dark:text-yellow-200">Perhatian: Kontrak Mendekati Expired</h3>
                <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                    Ada {{ $this->expiringContracts->count() }} kontrak yang perlu diperhatikan dalam waktu dekat.
                </p>
            </div>
        </div>
    </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid gap-6 lg:grid-cols-2">
        <!-- Recent Contracts Table -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Kontrak Terbaru</h2>
                @if(auth()->user()?->hasPermission('contracts.view'))
                <a href="{{ route('tickets.index') }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
                    Lihat Semua →
                </a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-3 text-left">No. Kontrak</th>
                            <th class="px-4 py-3 text-left">Judul Kontrak</th>
                            <th class="px-4 py-3 text-left">End Date</th>
                            <th class="px-4 py-3 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                        @forelse($this->recentContracts as $contract)
                        <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="contract-{{ $contract->id }}">
                            <td class="px-4 py-3 text-sm font-medium text-neutral-900 dark:text-white">
                                {{ $contract->contract_number }}
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->agreement_name ?? $contract->proposed_document_title ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">
                                {{ $contract->end_date?->format('d M Y') ?? '-' }}
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
                                @endphp
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $color === 'green' ? 'bg-green-500' : ($color === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                                    {{ $contract->status === 'terminated' ? 'Terminated' : ($contract->days_remaining > 0 ? $contract->days_remaining . ' hari' : 'Expired') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-neutral-500 dark:text-neutral-400">
                                Belum ada kontrak
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Contracts Needing Attention -->
        <div class="rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-white">Perlu Perhatian</h2>
            </div>
            <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                @forelse($this->expiringContracts as $contract)
                <div class="flex items-center gap-4 p-4" wire:key="expiring-{{ $contract->id }}">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full {{ $contract->status_color === 'red' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-yellow-100 dark:bg-yellow-900/30' }}">
                        <flux:icon name="exclamation-triangle" class="h-5 w-5 {{ $contract->status_color === 'red' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400' }}" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="truncate font-medium text-neutral-900 dark:text-white">
                            {{ $contract->contract_number }}
                        </p>
                        <p class="truncate text-sm text-neutral-500 dark:text-neutral-400">
                            {{ $contract->agreement_name ?? $contract->proposed_document_title ?? '-' }} • {{ $contract->division->name }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-medium {{ $contract->status_color === 'red' ? 'text-red-600 dark:text-red-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                            {{ $contract->status === 'terminated' ? 'Terminated' : ($contract->days_remaining > 0 ? $contract->days_remaining . ' hari' : 'Expired') }}
                        </p>
                        <p class="text-xs text-neutral-500 dark:text-neutral-400">
                            {{ $contract->end_date?->format('d M Y') ?? '-' }}
                        </p>
                    </div>
                </div>
                @empty
                <div class="flex flex-col items-center justify-center p-8 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30">
                        <flux:icon name="check-circle" class="h-8 w-8 text-green-600 dark:text-green-400" />
                    </div>
                    <p class="mt-4 font-medium text-neutral-900 dark:text-white">Semua Aman!</p>
                    <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Tidak ada kontrak yang perlu perhatian segera.
                    </p>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
