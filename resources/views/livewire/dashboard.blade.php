<?php

use App\Models\Contract;
use App\Models\Setting;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Collection;

new #[Layout('components.layouts.app')] class extends Component {
    public function getStatisticsProperty(): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['total' => 0, 'active' => 0, 'expiring_soon' => 0, 'critical' => 0, 'expired' => 0];
        }

        $query = Contract::query();

        // PIC only sees their own contracts
        if (method_exists($user, 'isPic') && $user->isPic()) {
            $query->forPic($user->id);
        }

        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        $total = (clone $query)->count();
        $active = (clone $query)->active()->count();
        $expiringSoon = (clone $query)->where('status', 'active')
            ->whereDate('end_date', '<=', now()->addDays($warningThreshold))
            ->whereDate('end_date', '>', now()->addDays($criticalThreshold))
            ->count();
        $critical = (clone $query)->critical()->count();
        $expired = (clone $query)->expired()->count();

        return [
            'total' => $total,
            'active' => $active,
            'expiring_soon' => $expiringSoon,
            'critical' => $critical,
            'expired' => $expired,
        ];
    }

    public function getRecentContractsProperty(): Collection
    {
        $user = auth()->user();
        if (!$user) {
            return collect();
        }

        $query = Contract::with(['division', 'pic', 'ticket'])
            ->orderBy('end_date', 'asc');

        if (method_exists($user, 'isPic') && $user->isPic()) {
            $query->forPic($user->id);
        }

        return $query->limit(10)->get();
    }

    public function getExpiringContractsProperty(): Collection
    {
        $user = auth()->user();
        if (!$user) {
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
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
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

        <!-- Expiring Soon (Yellow) -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900/30">
                    <flux:icon name="clock" class="h-6 w-6 text-yellow-600 dark:text-yellow-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Mendekati Expired</p>
                    <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ $this->statistics['expiring_soon'] }}</p>
                </div>
            </div>
        </div>

        <!-- Critical (Red) -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="exclamation-triangle" class="h-6 w-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Kritis</p>
                    <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ $this->statistics['critical'] }}</p>
                </div>
            </div>
        </div>

        <!-- Expired -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <div class="flex items-center gap-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800">
                    <flux:icon name="x-circle" class="h-6 w-6 text-neutral-600 dark:text-neutral-400" />
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Expired</p>
                    <p class="text-2xl font-bold text-neutral-600 dark:text-neutral-400">{{ $this->statistics['expired'] }}</p>
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
                <a href="{{ route('contracts.index') }}" class="text-sm text-blue-600 hover:underline dark:text-blue-400" wire:navigate>
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
