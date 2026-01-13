<?php

use App\Models\Ticket;
use App\Models\Division;
use App\Models\Department;
use App\Services\NotificationService;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    // Common fields
    public $division_id;
    public $department_id;
    public bool $has_financial_impact = false;
    public string $proposed_document_title = '';
    public $draft_document;
    public string $document_type = '';
    
    // Conditional: perjanjian/nda
    public string $counterpart_name = '';
    public string $agreement_start_date = '';
    public string $agreement_duration = '';
    public bool $is_auto_renewal = false;
    public string $renewal_period = '';
    public string $renewal_notification_days = '';
    public string $agreement_end_date = '';
    public string $termination_notification_days = '';
    
    // Conditional: surat_kuasa
    public string $kuasa_pemberi = '';
    public string $kuasa_penerima = '';
    public string $kuasa_start_date = '';
    public string $kuasa_end_date = '';
    
    // Common for all
    public bool $tat_legal_compliance = false;
    public $mandatory_documents = [];
    public $approval_document;

    public function mount(): void
    {
        // Auto-fill division dan department dari user login
        $user = auth()->user();
        $this->division_id = $user->division_id;
        $this->department_id = $user->department_id;
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('name')->get();
    }

    public function getDepartmentsProperty()
    {
        if (!$this->division_id) {
            return collect();
        }
        return Department::where('division_id', $this->division_id)->orderBy('name')->get();
    }

    public function save(): void
    {
        $user = auth()->user();

        if (!$user->hasPermission('contracts.create')) {
            $this->dispatch('notify', type: 'error', message: 'Anda tidak memiliki akses untuk membuat ticket.');
            return;
        }

        // Base validation rules
        $rules = [
            'has_financial_impact' => ['required', 'boolean'],
            'proposed_document_title' => ['required', 'string', 'max:255'],
            'draft_document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'document_type' => ['required', Rule::in(['perjanjian', 'nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'surat_lainnya'])],
            'tat_legal_compliance' => ['required', 'boolean'],
            'mandatory_documents.*' => ['nullable', 'file', 'max:10240'],
            'approval_document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        // Conditional validation based on document_type
        if (in_array($this->document_type, ['perjanjian', 'nda'])) {
            $rules['counterpart_name'] = ['required', 'string', 'max:255'];
            $rules['agreement_start_date'] = ['required', 'date'];
            $rules['agreement_duration'] = ['required', 'string', 'max:100'];
            $rules['is_auto_renewal'] = ['required', 'boolean'];
            
            if ($this->is_auto_renewal) {
                $rules['renewal_period'] = ['required', Rule::in(['yearly', 'monthly', 'weekly'])];
                $rules['renewal_notification_days'] = ['required', 'integer', 'min:1'];
            } else {
                $rules['agreement_end_date'] = ['required', 'date', 'after:agreement_start_date'];
            }
            
            $rules['termination_notification_days'] = ['nullable', 'integer', 'min:1'];
        } elseif ($this->document_type === 'surat_kuasa') {
            $rules['kuasa_pemberi'] = ['required', 'string', 'max:255'];
            $rules['kuasa_penerima'] = ['required', 'string', 'max:255'];
            $rules['kuasa_start_date'] = ['required', 'date'];
            $rules['kuasa_end_date'] = ['required', 'date', 'after:kuasa_start_date'];
        }

        $validated = $this->validate($rules);

        // Create ticket
        $ticket = Ticket::create([
            'division_id' => $this->division_id,
            'department_id' => $this->department_id,
            'has_financial_impact' => $validated['has_financial_impact'],
            'proposed_document_title' => $validated['proposed_document_title'],
            'document_type' => $validated['document_type'],
            // Conditional fields
            'counterpart_name' => $this->counterpart_name ?: null,
            'agreement_start_date' => $this->agreement_start_date ?: null,
            'agreement_duration' => $this->agreement_duration ?: null,
            'is_auto_renewal' => $this->is_auto_renewal,
            'renewal_period' => $this->is_auto_renewal ? ($this->renewal_period ?: null) : null,
            'renewal_notification_days' => $this->is_auto_renewal ? ($this->renewal_notification_days ?: null) : null,
            'agreement_end_date' => (!$this->is_auto_renewal && $this->agreement_end_date) ? $this->agreement_end_date : null,
            'termination_notification_days' => $this->termination_notification_days ?: null,
            'kuasa_pemberi' => $this->kuasa_pemberi ?: null,
            'kuasa_penerima' => $this->kuasa_penerima ?: null,
            'kuasa_start_date' => $this->kuasa_start_date ?: null,
            'kuasa_end_date' => $this->kuasa_end_date ?: null,
            'tat_legal_compliance' => $validated['tat_legal_compliance'],
            'status' => 'open',
            'created_by' => $user->id,
        ]);

        // Handle file uploads
        $ticketId = $ticket->id;
        
        if ($this->draft_document) {
            $draftPath = $this->draft_document->store("tickets/{$ticketId}/draft", 'public');
            $ticket->update(['draft_document_path' => $draftPath]);
        }
        
        if ($this->mandatory_documents && count($this->mandatory_documents) > 0) {
            $mandatoryPaths = [];
            foreach ($this->mandatory_documents as $file) {
                $path = $file->store("tickets/{$ticketId}/mandatory", 'public');
                $mandatoryPaths[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                ];
            }
            $ticket->update(['mandatory_documents_path' => $mandatoryPaths]);
        }
        
        if ($this->approval_document) {
            $approvalPath = $this->approval_document->store("tickets/{$ticketId}/approval", 'public');
            $ticket->update(['approval_document_path' => $approvalPath]);
        }

        // Send email notification to legal team
        $notificationService = app(NotificationService::class);
        $notificationService->notifyTicketCreated($ticket);

        session()->flash('success', 'Ticket berhasil dibuat dan notifikasi telah dikirim ke tim legal.');
        $this->redirect(route('tickets.index'), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-5xl">
    <!-- Header -->
    <div class="mb-6">
        <a href="{{ route('tickets.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
            <flux:icon name="arrow-left" class="h-4 w-4" />
            Kembali ke Daftar
        </a>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Buat Ticket Baru</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">Isi formulir di bawah untuk membuat ticket legal. Pertanyaan akan muncul secara dinamis berdasarkan jenis dokumen yang dipilih.</p>
    </div>

    <!-- Form -->
    <form wire:submit="save" class="space-y-6">
        <!-- Informasi Dasar -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">1. Informasi Dasar</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Division (readonly, auto-filled) -->
                <flux:field>
                    <flux:label>User Directorate (Divisi)</flux:label>
                    <flux:select wire:model="division_id" disabled>
                        @foreach($this->divisions as $division)
                        <option value="{{ $division->id }}" {{ $division->id == $this->division_id ? 'selected' : '' }}>{{ $division->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>Auto-filled dari akun Anda</flux:description>
                </flux:field>

                <!-- Department (readonly, auto-filled) -->
                <flux:field>
                    <flux:label>Departement</flux:label>
                    <flux:select wire:model="department_id" disabled>
                        <option value="">-</option>
                        @foreach($this->departments as $dept)
                        <option value="{{ $dept->id }}" {{ $dept->id == $this->department_id ? 'selected' : '' }}>{{ $dept->name }}</option>
                        @endforeach
                    </flux:select>
                    <flux:description>Auto-filled dari akun Anda</flux:description>
                </flux:field>

                <!-- Financial Impact -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Financial Impact (Income-Pemasukan/Expenditure-Pengeluaran) *</flux:label>
                    <flux:radio.group wire:model="has_financial_impact" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="has_financial_impact" />
                </flux:field>

                <!-- Usulan Judul Dokumen -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Usulan Judul Dokumen *</flux:label>
                    <flux:input wire:model="proposed_document_title" placeholder="Masukkan judul dokumen yang diusulkan" required />
                    <flux:error name="proposed_document_title" />
                </flux:field>

                <!-- Draft Usulan Dokumen -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Draft Usulan Dokumen (Opsional)</flux:label>
                    <input type="file" wire:model="draft_document" accept=".pdf,.doc,.docx" class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                    <flux:description>PDF atau Word, maksimal 10MB</flux:description>
                    <flux:error name="draft_document" />
                    <div wire:loading wire:target="draft_document" class="mt-2 text-sm text-blue-600">
                        Mengupload draft...
                    </div>
                </flux:field>

                <!-- Jenis Dokumen -->
                <flux:field class="sm:col-span-2">
                    <flux:label>Jenis Dokumen *</flux:label>
                    <flux:select wire:model.live="document_type" required>
                        <option value="">Pilih Jenis Dokumen</option>
                        <option value="perjanjian">Perjanjian/Adendum/Amandemen</option>
                        <option value="nda">Perjanjian Kerahasiaan (NDA)</option>
                        <option value="surat_kuasa">Surat Kuasa</option>
                        <option value="pendapat_hukum">Pendapat Hukum</option>
                        <option value="surat_pernyataan">Surat Pernyataan</option>
                        <option value="surat_lainnya">Surat Lainnya</option>
                    </flux:select>
                    <flux:error name="document_type" />
                </flux:field>
            </div>
        </div>

        <!-- Conditional Fields: Perjanjian/NDA -->
        @if(in_array($this->document_type, ['perjanjian', 'nda']))
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">2. Detail {{ $this->document_type === 'nda' ? 'NDA' : 'Perjanjian' }}</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field class="sm:col-span-2">
                    <flux:label>Counterpart / Nama Pihak Lainnya *</flux:label>
                    <flux:input wire:model="counterpart_name" placeholder="Nama pihak lain dalam perjanjian" required />
                    <flux:error name="counterpart_name" />
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal Perkiraan Mulainya {{ $this->document_type === 'nda' ? 'NDA' : 'Perjanjian' }} *</flux:label>
                    <flux:input type="date" wire:model="agreement_start_date" required />
                    <flux:error name="agreement_start_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Jangka Waktu {{ $this->document_type === 'nda' ? 'NDA' : 'Perjanjian' }} *</flux:label>
                    <flux:input wire:model="agreement_duration" placeholder="Contoh: 2 tahun, 12 bulan" required />
                    <flux:error name="agreement_duration" />
                </flux:field>

                <flux:field class="sm:col-span-2">
                    <flux:label>Pembaruan Otomatis *</flux:label>
                    <flux:radio.group wire:model.live="is_auto_renewal" variant="segmented" required>
                        <flux:radio value="1" label="Yes" />
                        <flux:radio value="0" label="No" />
                    </flux:radio.group>
                    <flux:error name="is_auto_renewal" />
                </flux:field>

                @if($this->is_auto_renewal)
                <flux:field>
                    <flux:label>Periode Pembaruan Otomatis *</flux:label>
                    <flux:select wire:model="renewal_period" required>
                        <option value="">Pilih Periode</option>
                        <option value="yearly">Per Tahun</option>
                        <option value="monthly">Per Bulan</option>
                        <option value="weekly">Per Minggu</option>
                    </flux:select>
                    <flux:error name="renewal_period" />
                </flux:field>

                <flux:field>
                    <flux:label>Jangka Waktu Notifikasi Sebelum Pembaruan Otomatis (Hari) *</flux:label>
                    <flux:input type="number" wire:model="renewal_notification_days" placeholder="Contoh: 30" required />
                    <flux:error name="renewal_notification_days" />
                </flux:field>
                @endif

                @if(!$this->is_auto_renewal)
                <flux:field >
                    <flux:label>Tanggal Berakhirnya {{ $this->document_type === 'nda' ? 'NDA' : 'Perjanjian' }} *</flux:label>
                    <flux:input type="date" wire:model="agreement_end_date" required />
                    <flux:error name="agreement_end_date" />
                </flux:field>

                <flux:field >
                    <flux:label>Jangka Waktu Notifikasi Sebelum Pengakhiran (Hari)</flux:label>
                    <flux:input type="number" wire:model="termination_notification_days" placeholder="Contoh: 60" />
                    <!-- <flux:description>Opsional - sistem akan mengirim notifikasi sebelum tanggal berakhir</flux:description> -->
                    <flux:error name="termination_notification_days" />
                </flux:field>
                @endif

                
            </div>
        </div>
        @endif

        <!-- Conditional Fields: Surat Kuasa -->
        @if($this->document_type === 'surat_kuasa')
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">2. Detail Surat Kuasa</h2>
            
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Pemberi Kuasa *</flux:label>
                    <flux:input wire:model="kuasa_pemberi" placeholder="Nama pemberi kuasa" required />
                    <flux:error name="kuasa_pemberi" />
                </flux:field>

                <flux:field>
                    <flux:label>Penerima Kuasa *</flux:label>
                    <flux:input wire:model="kuasa_penerima" placeholder="Nama penerima kuasa" required />
                    <flux:error name="kuasa_penerima" />
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal Perkiraan Mulainya Pemberian Kuasa *</flux:label>
                    <flux:input type="date" wire:model="kuasa_start_date" required />
                    <flux:error name="kuasa_start_date" />
                </flux:field>

                <flux:field>
                    <flux:label>Tanggal Berakhirnya Kuasa *</flux:label>
                    <flux:input type="date" wire:model="kuasa_end_date" required />
                    <flux:error name="kuasa_end_date" />
                </flux:field>
            </div>
        </div>
        @endif

        <!-- Common Fields for All Types -->
        @if($this->document_type)
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">{{ in_array($this->document_type, ['perjanjian', 'nda']) || $this->document_type === 'surat_kuasa' ? '3' : '2' }}. Dokumen Pendukung</h2>
            
            <div class="grid gap-4">
                <flux:field>
                    <flux:label>Kesesuaian dengan Turn-Around-Time Legal *</flux:label>
                    <flux:radio.group wire:model="tat_legal_compliance" variant="segmented" required>
                        <flux:radio value="1" label="Ya" />
                        <flux:radio value="0" label="Tidak" />
                    </flux:radio.group>
                    <flux:error name="tat_legal_compliance" />
                </flux:field>

                <flux:field>
                    <flux:label>Dokumen Wajib *</flux:label>
                    <input type="file" wire:model="mandatory_documents" multiple class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-purple-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-purple-700 hover:file:bg-purple-100 dark:text-neutral-400 dark:file:bg-purple-900/30 dark:file:text-purple-400" />
                    <flux:description>Akta Pendirian, Akta Susunan Direktur Komisaris, Akta Perubahan Terakhir, KTP, dll. (Maksimal 10MB per file, bisa upload multiple)</flux:description>
                    <flux:error name="mandatory_documents" />
                    <div wire:loading wire:target="mandatory_documents" class="mt-2 text-sm text-purple-600">
                        Mengupload dokumen wajib...
                    </div>
                </flux:field>

                <flux:field>
                    <flux:label>Legal Request Permit/Approval dari Head atau Leader Terkait *</flux:label>
                    <input type="file" wire:model="approval_document" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-orange-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-orange-700 hover:file:bg-orange-100 dark:text-neutral-400 dark:file:bg-orange-900/30 dark:file:text-orange-400" />
                    <flux:description>Screenshot email/korespondensi approval (PDF atau gambar, maksimal 5MB)</flux:description>
                    <flux:error name="approval_document" />
                    <div wire:loading wire:target="approval_document" class="mt-2 text-sm text-orange-600">
                        Mengupload approval document...
                    </div>
                </flux:field>
            </div>
        </div>
        @endif

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('tickets.index') }}" wire:navigate>
                <flux:button variant="ghost">Batal</flux:button>
            </a>
            <flux:button type="submit" variant="primary">
                Buat Ticket
            </flux:button>
        </div>
    </form>
</div>
