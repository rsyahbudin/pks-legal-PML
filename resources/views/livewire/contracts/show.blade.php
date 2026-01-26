<?php

use App\Models\Ticket;
use App\Models\Contract;
use App\Services\NotificationService;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    public Ticket $ticket;
    public bool $showRejectModal = false;
    public bool $showTerminateModal = false;
    public string $rejectionReason = '';
    public string $terminationReason = '';

    public function mount(int $contract): void
    {
        // Note: route parameter is called 'contract' for backward compatibility
        // but we're actually loading a ticket
        $this->ticket = Ticket::with([
            'division', 
            'department', 
            'creator', 
            'reviewer',
            'contract',
            'contract.status', // Ensure contract status is loaded
            'activityLogs.user',
            'status', // Load ticket status
            'documentType', // Load document type
        ])->findOrFail($contract);
    }

    public function moveToOnProcess(): void
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Hanya legal team yang dapat memproses ticket.');
            return;
        }

        if (!$this->ticket->canBeReviewed()) {
            $this->dispatch('notify', type: 'error', message: 'Ticket tidak dapat diproses.');
            return;
        }

        $oldStatus = $this->ticket->status?->code;
        $this->ticket->moveToOnProcess($user);

        // Send notification
        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'on_process');

        $this->dispatch('notify', type: 'success', message: 'Ticket berhasil dipindah ke status On Process.');

        // Refresh data
        $this->mount($this->ticket->id);
    }

    public function openRejectModal(): void
    {
        $this->showRejectModal = true;
    }

    public function rejectTicket(): void
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Hanya legal team yang dapat reject ticket.');
            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:10'],
        ]);

        $oldStatus = $this->ticket->status?->code;
        $this->ticket->reject($this->rejectionReason, $user);

        // Send notification
        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'rejected');

        $this->showRejectModal = false;
        $this->dispatch('notify', type: 'success', message: 'Ticket berhasil ditolak.');

        // Refresh data
        $this->mount($this->ticket->id);
    }

    public function moveToDone(): void
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Hanya legal team yang dapat menyelesaikan ticket.');
            return;
        }

        if ($this->ticket->status?->code !== 'on_process') {
            $this->dispatch('notify', type: 'error', message: 'Hanya ticket dengan status On Process yang dapat diselesaikan.');
            return;
        }

        $oldStatus = $this->ticket->status?->code;
        $this->ticket->moveToDone();
        
        // Refresh ticket to get updated status from database
        $this->ticket->refresh();

        // Create contract from ticket
        if (!$this->ticket->contract && $this->canCreateContract()) {
            $this->generateContract();
            $this->ticket->load('contract');
        }

        // Send notification
        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'done');

        // Refresh data
        $this->ticket = $this->ticket->fresh([
            'division', 
            'department', 
            'creator', 
            'reviewer',
            'contract.status', // Ensure deep load of contract status
            'activityLogs.user'
        ]);
        $this->mount($this->ticket->id);
    }

    public function generateContract()
    {
        \Log::info('=== GENERATE CONTRACT: START ===', [
            'ticket_id' => $this->ticket->id,
            'document_type' => $this->ticket->documentType?->code,
            'status' => $this->ticket->status?->code,
        ]);
        
        // Only allow contract creation for specific document types
        $contractableTypes = ['perjanjian', 'nda', 'surat_kuasa'];
        
        if (!in_array($this->ticket->documentType?->code, $contractableTypes)) {
            \Log::warning('Document type not contractable', ['type' => $this->ticket->documentType?->code]);
            $this->dispatch('notify', type: 'error', message: 'Tipe dokumen ini tidak memerlukan contract.');
            return;
        }

        if ($this->ticket->status?->code !== 'done') {
            \Log::warning('Ticket status not done', ['status' => $this->ticket->status?->code]);
            $this->dispatch('notify', type: 'error', message: 'Ticket harus berstatus Done untuk membuat contract.');
            return;
        }

        if ($this->ticket->contract) {
            \Log::warning('Contract already exists');
            $this->dispatch('notify', type: 'error', message: 'Contract sudah dibuat untuk ticket ini.');
            return;
        }

        try {
            \Log::info('Calling ticket->createContract()');
            $contract = $this->ticket->createContract();
            \Log::info('Contract created successfully', [
                'contract_id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'contract_status' => $contract->status,
            ]);
            
            // Auto-close ticket if contract is created with expired status
            if ($contract->status?->code === 'expired') {
                $this->ticket->update(['status' => 'closed']);
                $this->ticket->logActivity('Ticket ditutup otomatis karena contract sudah expired');
                $this->dispatch('notify', type: 'warning', message: "Contract #{$contract->contract_number} dibuat dengan status Expired. Ticket ditutup otomatis.");
            } else {
                $this->dispatch('notify', type: 'success', message: "Contract #{$contract->contract_number} berhasil dibuat.");
            }
        } catch (\Exception $e) {
            \Log::error('Contract creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('notify', type: 'error', message: 'Gagal membuat contract: ' . $e->getMessage());
        }
    }
    
    public function canCreateContract(): bool
    {
        $contractableTypes = ['perjanjian', 'nda', 'surat_kuasa'];
        
        return $this->ticket->status?->code === 'done' 
            && !$this->ticket->contract
            && in_array($this->ticket->documentType?->code, $contractableTypes);
    }

    public function openTerminateModal(): void
    {
        $this->showTerminateModal = true;
    }

    public function terminateContract(): void
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Hanya legal team yang dapat terminate contract.');
            return;
        }

        if (!$this->ticket->contract) {
            $this->dispatch('notify', type: 'error', message: 'Ticket tidak memiliki contract.');
            return;
        }

        $this->validate([
            'terminationReason' => ['required', 'string', 'min:10'],
        ]);

        $oldStatus = $this->ticket->contract->status?->code;
        $this->ticket->contract->terminate($this->terminationReason);

        // Send notification
        $notificationService = app(NotificationService::class);
        $notificationService->notifyContractStatusChanged($this->ticket->contract, $oldStatus, 'terminated');

        $this->showTerminateModal = false;
        $this->dispatch('notify', type: 'success', message: 'Contract berhasil diterminasi dan ticket ditutup.');
        
        // Refresh data
        $this->mount($this->ticket->id);
    }

    public function moveToClosedDirectly(): void
    {
        $user = auth()->user();
        
        if (!$user->hasAnyRole(['super-admin', 'legal'])) {
            $this->dispatch('notify', type: 'error', message: 'Hanya legal team yang dapat menutup ticket.');
            return;
        }

        if ($this->ticket->status?->code !== 'on_process') {
            $this->dispatch('notify', type: 'error', message: 'Hanya ticket dengan status On Process yang dapat ditutup.');
            return;
        }

        $oldStatus = $this->ticket->status?->code;
        $this->ticket->moveToClosedDirectly();

        // Send notification
        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketStatusChanged($this->ticket, $oldStatus, 'closed');

        $this->dispatch('notify', type: 'success', message: 'Ticket berhasil ditutup.');
        
        // Refresh data
        $this->mount($this->ticket->id);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    <!-- Header with Back Button -->
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
            <flux:icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Daftar
        </a>
        <div class="flex items-start justify-between">
            <div>
                <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">{{ $ticket->ticket_number }}</h1>
            </div>
            @php
                $statusBadge = match($ticket->status_color) {
                    'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                    'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                    'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                    'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                    'gray' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
                    default => 'bg-neutral-100 text-neutral-800',
                };
            @endphp
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium {{ $statusBadge }}">
                    {{ $ticket->status_label }}
                </span>
                <span class="inline-flex items-center rounded-full px-3 py-1.5 text-sm font-medium {{ $ticket->aging_display === '-' ? 'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' }}">
                    <svg class="mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ $ticket->aging_display }}
                </span>
            </div>
        </div>
    </div>

    <!-- Action Buttons for Legal Team -->
    @if(auth()->user()->hasAnyRole(['super-admin', 'legal']))
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">
        <h3 class="mb-3 font-semibold text-blue-900 dark:text-blue-300">Legal Team Actions</h3>
        <div class="flex flex-wrap gap-2">
            <!-- Edit Button (visible anytime for legal) -->
            <a href="{{ route('tickets.edit', $ticket->id) }}" wire:navigate>
                <flux:button variant="ghost" icon="pencil">
                    Edit Ticket
                </flux:button>
            </a>

            @if($ticket->status?->code === 'open')
                <flux:button wire:click="moveToOnProcess" variant="primary" icon="play">
                    Process Ticket
                </flux:button>
                <flux:button wire:click="openRejectModal" variant="danger" icon="x-mark">
                    Reject Ticket
                </flux:button>
            @elseif($ticket->status?->code === 'on_process')
                @php
                    $isContractable = in_array($ticket->documentType?->code, ['perjanjian', 'nda', 'surat_kuasa']);
                @endphp
                
                @if($isContractable)
                    <flux:button wire:click="moveToDone" variant="primary" icon="check">
                        Mark as Done (Create Contract)
                    </flux:button>
                @else
                    <flux:button wire:click="moveToClosedDirectly" variant="primary" icon="check-circle">
                        Close Ticket
                    </flux:button>
                @endif
            @endif

            @if($ticket->contract && $ticket->contract->status?->code === 'active')
                <flux:button wire:click="openTerminateModal" variant="danger" icon="x-circle">
                    Terminate Contract
                </flux:button>
            @endif
        </div>
    </div>
    @endif

    <!-- Ticket Information -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Informasi Ticket</h2>
        
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Nomor Ticket</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->ticket_number }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Jenis Dokumen</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->document_type_label }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Judul Dokumen (Usulan)</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->proposed_document_title }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Divisi</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->division->name }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Departemen</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->department?->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Dibuat Oleh</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->creator->name }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Dibuat</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->created_at->format('d M Y H:i') }}</p>
            </div>
            
            @if($ticket->reviewed_by)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Direview Oleh</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->reviewer->name }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Review</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->reviewed_at->format('d M Y H:i') }}</p>
            </div>
            @endif



            @if($ticket->rejection_reason)
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Alasan Penolakan</p>
                <p class="font-medium text-red-600 dark:text-red-400">{{ $ticket->rejection_reason }}</p>
            </div>
            @endif

            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Financial Impact</p>
                <p class="font-medium text-neutral-900 dark:text-white">
                    @if($ticket->has_financial_impact)
                        <flux:badge color="green">Yes</flux:badge>
                    @else
                        <flux:badge color="neutral">No</flux:badge>
                    @endif
                </p>
            </div>

            @if($ticket->has_financial_impact && $ticket->payment_type)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Jenis Pembayaran</p>
                <p class="font-medium text-neutral-900 dark:text-white">
                    @if($ticket->payment_type === 'pay')
                        <flux:badge color="orange">Pay (Bayar)</flux:badge>
                    @elseif($ticket->payment_type === 'receive_payment')
                        <flux:badge color="blue">Receive Payment</flux:badge>
                    @endif
                </p>
            </div>
            
            @if($ticket->payment_type === 'pay' && $ticket->recurring_description)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Recurring Description</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->recurring_description }}</p>
            </div>
            @endif
            @endif

            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Turn Around Time Legal</p>
                <p class="font-medium text-neutral-900 dark:text-white">
                    @if($ticket->tat_legal_compliance)
                        <flux:badge color="green">Yes</flux:badge>
                    @else
                        <flux:badge color="neutral">No</flux:badge>
                    @endif
                </p>
            </div>


        </div>

        <!-- Document-specific details -->
        @if(in_array($ticket->documentType?->code, ['perjanjian', 'nda']) && $ticket->counterpart_name)
        <div class="mt-6 border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <h3 class="mb-3 font-semibold text-neutral-900 dark:text-white">Detail {{ $ticket->documentType?->code === 'nda' ? 'NDA' : 'Perjanjian' }}</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Counterpart</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->counterpart_name }}</p>
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Mulai</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->agreement_start_date?->format('d M Y') ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Jangka Waktu</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->agreement_duration }}</p>
                </div>
                @if(!$ticket->is_auto_renewal && $ticket->agreement_end_date)
                <div>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Berakhir</p>
                    <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->agreement_end_date->format('d M Y') }}</p>
                </div>
                @endif
            </div>
        </div>
        @endif


    </div>

    <!-- Contract Information (if exists) -->
    @if($ticket->contract)
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Informasi Contract</h2>
        
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Nomor Contract</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->contract_number }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Status Contract</p>
                @php
                    $color = $ticket->contract->status?->color ?? 'neutral';
                @endphp
                <flux:badge :color="$color" size="sm" inset="top bottom">{{ $ticket->contract->status?->name ?? 'Unknown' }}</flux:badge>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Mulai</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->start_date?->format('d M Y') ?? '-' }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Tanggal Berakhir</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->end_date?->format('d M Y') ?? '-' }}</p>
            </div>

            @if($ticket->documentType?->code === 'surat_kuasa' && $ticket->kuasa_pemberi)
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Pemberi Kuasa</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->kuasa_pemberi }}</p>
            </div>
            <div>
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Penerima Kuasa</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->kuasa_penerima }}</p>
            </div>
            @endif
            
            @if($ticket->contract->terminated_at)
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Diterminasi Pada</p>
                <p class="font-medium text-neutral-900 dark:text-white">{{ $ticket->contract->terminated_at->format('d M Y H:i') }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">Alasan Terminasi</p>
                <p class="font-medium text-red-600 dark:text-red-400">{{ $ticket->contract->termination_reason }}</p>
            </div>
            @endif
        </div>
    </div>
    @endif

    <!-- Uploaded Documents -->
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Dokumen Terupload</h2>
        
        <div class="space-y-4">
            @if($ticket->draft_document_path)
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Draft Dokumen</p>
                <a href="{{ Storage::url($ticket->draft_document_path) }}" download target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-blue-50 px-3 py-2 text-sm text-blue-700 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50">
                    <flux:icon name="document-text" class="h-4 w-4" />
                    <span>Download Draft</span>
                    <flux:icon name="arrow-down-tray" class="h-4 w-4" />
                </a>
            </div>
            @endif

            @if($ticket->mandatory_documents_path && count($ticket->mandatory_documents_path) > 0)
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Dokumen Wajib ({{ count($ticket->mandatory_documents_path) }} file)</p>
                <div class="space-y-2">
                    @foreach($ticket->mandatory_documents_path as $index => $doc)
                    <a href="{{ Storage::url($doc['path']) }}" download target="_blank" class="flex items-center gap-2 rounded-lg bg-purple-50 px-3 py-2 text-sm text-purple-700 hover:bg-purple-100 dark:bg-purple-900/30 dark:text-purple-400 dark:hover:bg-purple-900/50">
                        <flux:icon name="document" class="h-4 w-4" />
                        <span class="flex-1">{{ $doc['name'] }}</span>
                        <flux:icon name="arrow-down-tray" class="h-4 w-4" />
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            @if($ticket->approval_document_path)
            <div>
                <p class="mb-2 text-sm font-medium text-neutral-700 dark:text-neutral-300">Dokumen Approval</p>
                <a href="{{ Storage::url($ticket->approval_document_path) }}" download target="_blank" class="inline-flex items-center gap-2 rounded-lg bg-orange-50 px-3 py-2 text-sm text-orange-700 hover:bg-orange-100 dark:bg-orange-900/30 dark:text-orange-400 dark:hover:bg-orange-900/50">
                    <flux:icon name="document-check" class="h-4 w-4" />
                    <span>Download Approval</span>
                    <flux:icon name="arrow-down-tray" class="h-4 w-4" />
                </a>
            </div>
            @endif

            @if(!$ticket->draft_document_path && (!$ticket->mandatory_documents_path || count($ticket->mandatory_documents_path) == 0) && !$ticket->approval_document_path)
            <p class="text-center text-sm text-neutral-500 dark:text-neutral-400">Tidak ada dokumen yang diupload</p>
            @endif
        </div>
    </div>

    <!-- Activity Log -->
    @php
        // Merge ticket and contract activity logs
        $allLogs = $ticket->activityLogs;
        if ($ticket->contract && $ticket->contract->activityLogs) {
            $allLogs = $allLogs->merge($ticket->contract->activityLogs);
        }
        $allLogs = $allLogs->sortByDesc('created_at');
    @endphp
    
    @if($allLogs->count() > 0)
    <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
        <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Activity Log</h2>
        
        <div class="space-y-4">
            @foreach($allLogs as $log)
            <div class="flex gap-3 border-l-2 border-neutral-200 pl-4 dark:border-neutral-700">
                <div class="flex-1">
                    <p class="text-sm font-medium text-neutral-900 dark:text-white">{{ $log->action }}</p>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        {{ $log->user?->name ?? 'System' }} • {{ $log->created_at->format('d M Y H:i') }}
                    </p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Reject Modal -->
    <flux:modal name="reject-modal" :open="$showRejectModal" wire:model="showRejectModal">
        <form wire:submit="rejectTicket" class="space-y-6">
            <div>
                <flux:heading size="lg">Reject Ticket</flux:heading>
                <flux:subheading>Berikan alasan penolakan ticket ini</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Alasan Penolakan *</flux:label>
                <flux:textarea wire:model="rejectionReason" rows="4" placeholder="Jelaskan alasan penolakan..." required />
                <flux:error name="rejectionReason" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" wire:click="$set('showRejectModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="danger">Reject Ticket</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Terminate Contract Modal -->
    <flux:modal name="terminate-modal" :open="$showTerminateModal" wire:model="showTerminateModal">
        <form wire:submit="terminateContract" class="space-y-6">
            <div>
                <flux:heading size="lg">Terminate Contract</flux:heading>
                <flux:subheading>Berikan alasan terminasi contract ini</flux:subheading>
            </div>

            <flux:field>
                <flux:label>Alasan Terminasi *</flux:label>
                <flux:textarea wire:model="terminationReason" rows="4" placeholder="Jelaskan alasan terminasi..." required />
                <flux:error name="terminationReason" />
            </flux:field>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:button variant="ghost" type="button" wire:click="$set('showTerminateModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="danger">Terminate Contract</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
