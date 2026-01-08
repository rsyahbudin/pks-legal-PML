<?php

use App\Models\Notification;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component {
    public bool $showDropdown = false;

    public function getNotificationsProperty()
    {
        return Notification::forUser(auth()->id())
            ->latest()
            ->limit(10)
            ->get();
    }

    public function getUnreadCountProperty(): int
    {
        return Notification::forUser(auth()->id())
            ->unread()
            ->count();
    }

    public function markAsRead(int $id): void
    {
        $notification = Notification::findOrFail($id);
        if ($notification->user_id === auth()->id()) {
            $notification->markAsRead();
        }
    }

    public function markAllAsRead(): void
    {
        Notification::forUser(auth()->id())
            ->unread()
            ->update(['read_at' => now()]);
    }

    #[On('notification-created')]
    public function refreshNotifications(): void
    {
        // This will automatically refresh the component
    }
}; ?>

<div class="relative" x-data="{ open: false }">
    <button 
        @click="open = !open"
        class="relative rounded-lg p-2 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700 dark:text-neutral-400 dark:hover:bg-zinc-800 dark:hover:text-neutral-200"
    >
        <flux:icon name="bell" class="h-5 w-5" />
        @if($this->unreadCount > 0)
        <span class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
            {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
        </span>
        @endif
    </button>

    <div 
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 top-full z-[1000] mt-2 w-80 rounded-xl border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-zinc-900"
    >
        <div class="flex items-center justify-between border-b border-neutral-200 p-4 dark:border-neutral-700">
            <h3 class="font-semibold text-neutral-900 dark:text-white">Notifikasi</h3>
            @if($this->unreadCount > 0)
            <button wire:click="markAllAsRead" class="text-xs text-blue-600 hover:underline dark:text-blue-400">
                Tandai semua dibaca
            </button>
            @endif
        </div>

        <div class="max-h-80 divide-y divide-neutral-100 overflow-y-auto dark:divide-neutral-800">
            @forelse($this->notifications as $notification)
            <div 
                wire:key="notif-{{ $notification->id }}"
                class="flex gap-3 p-4 {{ !$notification->isRead() ? 'bg-blue-50 dark:bg-blue-900/10' : '' }}"
            >
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $notification->type === 'warning' ? 'bg-yellow-100 dark:bg-yellow-900/30' : 'bg-red-100 dark:bg-red-900/30' }}">
                    <flux:icon name="exclamation-triangle" class="h-4 w-4 {{ $notification->type === 'warning' ? 'text-yellow-600' : 'text-red-600' }}" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ $notification->title }}</p>
                    <p class="truncate text-xs text-neutral-500 dark:text-neutral-400">{{ $notification->message }}</p>
                    <p class="mt-1 text-xs text-neutral-400 dark:text-neutral-500">{{ $notification->created_at->diffForHumans() }}</p>
                </div>
                @if(!$notification->isRead())
                <button wire:click="markAsRead({{ $notification->id }})" class="shrink-0 text-neutral-400 hover:text-neutral-600">
                    <flux:icon name="check" class="h-4 w-4" />
                </button>
                @endif
            </div>
            @empty
            <div class="p-8 text-center text-neutral-500 dark:text-neutral-400">
                <flux:icon name="bell-slash" class="mx-auto h-8 w-8 text-neutral-300 dark:text-neutral-600" />
                <p class="mt-2">Belum ada notifikasi</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
