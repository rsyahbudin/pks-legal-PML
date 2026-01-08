<?php

use App\Models\Contract;
use App\Models\Notification;
use App\Mail\ContractExpiringMail;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Mail;

new #[Layout('components.layouts.app')] class extends Component {
    public Contract $contract;

    public function mount(Contract $contract): void
    {
        $this->contract = $contract->load(['partner', 'division', 'department', 'pic', 'creator', 'activityLogs.user']);
    }

    public function sendReminder(): void
    {
        $user = auth()->user();
        
        if (!$user || !$user->hasPermission('contracts.send_reminder')) {
            session()->flash('error', 'Anda tidak memiliki akses untuk mengirim reminder.');
            return;
        }

        // Get CC emails from sub-division or division
        $ccEmails = [];
        if ($this->contract->department && $this->contract->department->cc_emails) {
            $ccEmails = array_filter(array_map('trim', explode(',', $this->contract->department->cc_emails)));
        } elseif ($this->contract->division && $this->contract->division->cc_emails) {
            $ccEmails = array_filter(array_map('trim', explode(',', $this->contract->division->cc_emails)));
        }

        // Send to PIC with CC, reply-to logged-in user's email
        $picEmail = $this->contract->pic_email;
        $picName = $this->contract->pic_name;

        if ($picEmail) {
            $mail = Mail::to($picEmail);
            
            if (!empty($ccEmails)) {
                $mail->cc($ccEmails);
            }
            
            // Pass replyTo through Mailable constructor (for manual reminder)
            $mail->send(new ContractExpiringMail(
                $this->contract, 
                $this->contract->days_remaining,
                $user->email,
                $user->name
            ));
        }

        // Create internal notification only if PIC is a registered user
        if ($this->contract->pic_id) {
            Notification::create([
                'user_id' => $this->contract->pic_id,
                'title' => 'Reminder: ' . $this->contract->contract_number,
                'message' => "Kontrak dengan {$this->contract->partner->display_name} akan berakhir dalam {$this->contract->days_remaining} hari.",
                'type' => $this->contract->days_remaining <= 30 ? 'critical' : 'warning',
                'data' => [
                    'contract_id' => $this->contract->id,
                    'sent_by' => $user->name,
                ],
            ]);
        }

        $ccInfo = !empty($ccEmails) ? ' (CC: ' . implode(', ', $ccEmails) . ')' : '';
        session()->flash('success', 'Reminder berhasil dikirim ke ' . $picName . $ccInfo);
    }
}; ?>

<div class="mx-auto max-w-4xl">
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('contracts.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
                    <flux:icon name="arrow-left" class="h-4 w-4" />
                    Kembali ke Daftar
                </a>
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $contract->contract_number }}</h1>
                <p class="mt-1 text-neutral-500 dark:text-neutral-400">{{ $contract->partner->display_name }}</p>
            </div>
            <div class="flex items-center gap-2">
                @php
                    $color = $contract->status_color;
                    $badgeClass = match($color) {
                        'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-200 dark:border-green-800',
                        'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 border-yellow-200 dark:border-yellow-800',
                        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800',
                        default => 'bg-neutral-100 text-neutral-800 dark:bg-neutral-800 dark:text-neutral-300 border-neutral-200 dark:border-neutral-700',
                    };
                @endphp
                <span class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium {{ $badgeClass }}">
                    <span class="h-2 w-2 rounded-full {{ $color === 'green' ? 'bg-green-500' : ($color === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                    {{ $contract->status_label }}
                    @if($contract->days_remaining > 0)
                        ({{ $contract->days_remaining }} hari lagi)
                    @elseif($contract->days_remaining == 0)
                        (Hari ini)
                    @else
                        (Expired)
                    @endif
                </span>
                @if(auth()->user()?->hasPermission('contracts.send_reminder'))
                <flux:button variant="ghost" icon="envelope" wire:click="sendReminder" wire:confirm="Kirim email reminder ke PIC kontrak ini?">
                    Kirim Reminder
                </flux:button>
                @endif
                @if(auth()->user()?->hasPermission('contracts.edit'))
                <a href="{{ route('contracts.edit', $contract) }}" wire:navigate>
                    <flux:button variant="primary" icon="pencil">Edit</flux:button>
                </a>
                @endif
            </div>
        </div>

        @if(session('success'))
        <div class="mb-6 rounded-lg bg-green-50 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-200">
            {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="mb-6 rounded-lg bg-red-50 p-4 text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ session('error') }}
        </div>
        @endif

        <!-- Contract Details -->
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Detail Kontrak</h2>
                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Nomor Kontrak</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $contract->contract_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Status</dt>
                            <dd class="mt-1 font-medium capitalize text-neutral-900 dark:text-white">{{ $contract->status }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Mulai</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $contract->start_date->format('d F Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Berakhir</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">
                                @if($contract->is_auto_renewal)
                                <flux:badge color="blue">Auto Renewal</flux:badge>
                                @elseif($contract->end_date)
                                {{ $contract->end_date->format('d F Y') }}
                                @else
                                -
                                @endif
                            </dd>
                        </div>
                        @if($contract->description)
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Deskripsi</dt>
                            <dd class="mt-1 text-neutral-900 dark:text-white">{{ $contract->description }}</dd>
                        </div>
                        @endif
                        @if($contract->document_path)
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Dokumen</dt>
                            <dd class="mt-1">
                                <a href="{{ Storage::url($contract->document_path) }}" target="_blank" class="inline-flex items-center gap-2 text-blue-600 hover:underline dark:text-blue-400">
                                    <flux:icon name="document-arrow-down" class="h-4 w-4" />
                                    Download Dokumen
                                </a>
                            </dd>
                        </div>
                        @endif
                    </dl>
                </div>

                <!-- Activity Log -->
                @if($contract->activityLogs->isNotEmpty())
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Riwayat Aktivitas</h2>
                    <div class="space-y-4">
                        @foreach($contract->activityLogs->take(5) as $log)
                        <div class="flex gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-neutral-100 dark:bg-neutral-800">
                                <flux:icon name="{{ $log->action === 'created' ? 'plus' : ($log->action === 'updated' ? 'pencil' : 'trash') }}" class="h-4 w-4 text-neutral-600 dark:text-neutral-400" />
                            </div>
                            <div>
                                <p class="text-sm text-neutral-900 dark:text-white">
                                    <span class="font-medium">{{ $log->user?->name ?? 'System' }}</span>
                                    {{ $log->action_label }} kontrak
                                </p>
                                @if($log->changes_description)
                                <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-300">{{ $log->changes_description }}</p>
                                @endif
                                <p class="text-xs text-neutral-500 dark:text-neutral-400 {{ $log->changes_description ? 'mt-1' : '' }}">
                                    {{ $log->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                    <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Informasi Terkait</h2>
                    <dl class="space-y-4">
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Partner</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $contract->partner->display_name }}</dd>
                            @if($contract->partner->email)
                            <dd class="text-sm text-neutral-500 dark:text-neutral-400">{{ $contract->partner->email }}</dd>
                            @endif
                        </div>
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Divisi</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $contract->division->name }}</dd>
                        </div>
                        @if($contract->department)
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Departemen</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $contract->department->name }}</dd>
                        </div>
                        @endif
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">PIC</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $contract->pic_name }}</dd>
                            <dd class="text-sm text-neutral-500 dark:text-neutral-400">{{ $contract->pic_email }}</dd>
                        </div>
                        @if($contract->creator)
                        <div>
                            <dt class="text-sm text-neutral-500 dark:text-neutral-400">Dibuat oleh</dt>
                            <dd class="mt-1 font-medium text-neutral-900 dark:text-white">{{ $contract->creator->name }}</dd>
                            <dd class="text-sm text-neutral-500 dark:text-neutral-400">{{ $contract->created_at->format('d M Y, H:i') }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
