<?php

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Division;
use App\Models\Partner;
use App\Models\Department;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public Contract $contract;

    public string $contract_number = '';
    public string $agreement_name = '';
    public string $proposed_document_title = '';
    public string $document_type = 'lainnya';
    public $financial_impact = null;
    public $tat_legal_compliance = null;
    public string $partner_id = '';
    public string $division_id = '';
    public ?int $department_id = null;
    public bool $is_auto_renewal = false;
    public string $pic_type = 'user'; // 'user' or 'manual'
    public string $pic_id = '';
    public string $pic_name = '';
    public string $pic_email = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $description = '';
    public string $status = 'draft';
    public $draft_document;
    public $mandatory_documents = [];
    public $approval_document;

    public function mount(Contract $contract): void
    {
        $this->contract = $contract;
        $this->contract_number = $contract->contract_number;
        $this->agreement_name = $contract->agreement_name ?? '';
        $this->proposed_document_title = $contract->proposed_document_title ?? '';
        $this->document_type = $contract->document_type ?? 'lainnya';
        // Convert boolean to string for radio buttons
        $this->financial_impact = $contract->financial_impact !== null ? (string)(int)$contract->financial_impact : null;
        $this->tat_legal_compliance = $contract->tat_legal_compliance !== null ? (string)(int)$contract->tat_legal_compliance : null;
        $this->partner_id = (string) $contract->partner_id;
        $this->division_id = (string) $contract->division_id;
        $this->department_id = $contract->department_id;
        $this->is_auto_renewal = (bool) $contract->is_auto_renewal;
        
        if ($contract->pic_id) {
            $this->pic_type = 'user';
            $this->pic_id = (string) $contract->pic_id;
        } else {
            $this->pic_type = 'manual';
            $this->pic_name = $contract->pic_name ?? '';
            $this->pic_email = $contract->pic_email ?? '';
        }
        
        $this->start_date = $contract->start_date->format('Y-m-d');
        $this->end_date = $contract->end_date ? $contract->end_date->format('Y-m-d') : '';
        $this->description = $contract->description ?? '';
        $this->status = $contract->status;
    }

    public function getPartnersProperty()
    {
        return Partner::active()->orderBy('name')->get();
    }

    public function getDivisionsProperty()
    {
        return Division::active()->with('departments')->orderBy('name')->get();
    }

    public function getDepartmentsProperty()
    {
        if (!$this->division_id) {
            return collect();
        }
        return Department::where('division_id', $this->division_id)->orderBy('name')->get();
    }

    public function getPicsProperty()
    {
        return User::whereNotNull('role_id')
            ->orderBy('name')
            ->get();
    }

    public function save(): void
    {
        $user = auth()->user();

        if (!$user->hasPermission('contracts.edit')) {
            $this->dispatch('notify', type: 'error', message: 'Anda tidak memiliki akses untuk mengedit kontrak.');
            return;
        }

        $rules = [
            'contract_number' => ['required', 'string', 'max:100', Rule::unique('contracts')->ignore($this->contract->id)],
            'agreement_name' => ['required', 'string', 'max:255'],
            'proposed_document_title' => ['nullable', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(['nda', 'surat_kuasa', 'pendapat_hukum', 'surat_pernyataan', 'lainnya'])],
            'financial_impact' => ['nullable', 'boolean'],
            'tat_legal_compliance' => ['nullable', 'boolean'],
            'partner_id' => ['required', 'exists:partners,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'start_date' => ['required', 'date'],
            'end_date' => [$this->is_auto_renewal ? 'nullable' : 'required', 'date', 'after_or_equal:start_date'],
            'is_auto_renewal' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'active', 'expired', 'terminated'])],
            'draft_document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'mandatory_documents.*' => ['nullable', 'file', 'max:10240'],
            'approval_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'pic_type' => ['required', Rule::in(['user', 'manual'])],
        ];

        if ($this->pic_type === 'user') {
            $rules['pic_id'] = ['required', 'exists:users,id'];
        } else {
            $rules['pic_name'] = ['required', 'string', 'max:255'];
            $rules['pic_email'] = ['required', 'email', 'max:255'];
        }

        $validated = $this->validate($rules);

        $oldValues = $this->contract->toArray();

        $this->contract->update([
            'contract_number' => $validated['contract_number'],
            'agreement_name' => $validated['agreement_name'],
            'proposed_document_title' => $validated['proposed_document_title'] ?? null,
            'document_type' => $validated['document_type'],
            'financial_impact' => $validated['financial_impact'] ?? null,
            'tat_legal_compliance' => $validated['tat_legal_compliance'] ?? null,
            'partner_id' => $validated['partner_id'],
            'division_id' => $validated['division_id'],
            'department_id' => $validated['department_id'] ?? null,
            'pic_id' => $this->pic_type === 'user' ? $validated['pic_id'] : null,
            'pic_name' => $this->pic_type === 'manual' ? $validated['pic_name'] : null,
            'pic_email' => $this->pic_type === 'manual' ? $validated['pic_email'] : null,
            'start_date' => $validated['start_date'],
            'end_date' => $this->is_auto_renewal ? null : $validated['end_date'],
            'is_auto_renewal' => $this->is_auto_renewal,
            'description' => $validated['description'],
            'status' => $validated['status'],
        ]);

        // Handle file uploads with organized structure
        $contractId = $this->contract->id;
        
        if ($this->draft_document) {
            $draftPath = $this->draft_document->store("contracts/{$contractId}/draft", 'public');
            $this->contract->update(['draft_document_path' => $draftPath]);
        }
        
        if ($this->mandatory_documents && count($this->mandatory_documents) > 0) {
            $mandatoryPaths = [];
            foreach ($this->mandatory_documents as $file) {
                $path = $file->store("contracts/{$contractId}/mandatory", 'public');
                $mandatoryPaths[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                ];
            }
            $this->contract->update(['mandatory_documents_path' => $mandatoryPaths]);
        }
        
        if ($this->approval_document) {
            $approvalPath = $this->approval_document->store("contracts/{$contractId}/approval", 'public');
            $this->contract->update(['approval_document_path' => $approvalPath]);
        }

        ActivityLog::logUpdated($this->contract, $oldValues);

        session()->flash('success', 'Kontrak berhasil diperbarui.');
        $this->redirect(route('contracts.show', $this->contract), navigate: true);
    }
}; ?>

<div class="mx-auto max-w-4xl">
        <!-- Header -->
        <div class="mb-6">
            <a href="{{ route('contracts.show', $contract) }}" class="mb-2 inline-flex items-center gap-1 text-sm text-neutral-500 hover:text-neutral-700 dark:text-neutral-400 dark:hover:text-neutral-200" wire:navigate>
                <flux:icon name="arrow-left" class="h-4 w-4" />
                Kembali ke Detail
            </a>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Edit Kontrak</h1>
            <p class="mt-1 text-neutral-500 dark:text-neutral-400">{{ $contract->contract_number }}</p>
        </div>

        <!-- Form -->
        <form wire:submit="save" class="space-y-6">
            <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
                <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Informasi Kontrak</h2>
                
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field class="sm:col-span-2 read-only:cursor-not-allowed ">
                        <flux:label>Nomor Kontrak</flux:label>
                        <flux:input wire:model="contract_number" readonly />
                        <flux:error name="contract_number" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Nama Agreement</flux:label>
                        <flux:input wire:model="agreement_name" placeholder="Nama Agreement" required />
                        <flux:error name="agreement_name" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Usulan Judul Dokumen</flux:label>
                        <flux:input wire:model="proposed_document_title" placeholder="Usulan Judul Dokumen (Opsional)" />
                        <flux:error name="proposed_document_title" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Jenis Dokumen</flux:label>
                        <flux:select wire:model="document_type" required>
                            <option value="">Pilih Jenis Dokumen</option>
                            <option value="nda">NDA</option>
                            <option value="surat_kuasa">Surat Kuasa</option>
                            <option value="pendapat_hukum">Pendapat Hukum</option>
                            <option value="surat_pernyataan">Surat Pernyataan</option>
                            <option value="lainnya">Surat Lainnya</option>
                        </flux:select>
                        <flux:error name="document_type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Financial Impact (Income-Pemasukan/Expenditure-Pengeluaran)</flux:label>
                        <flux:radio.group wire:model="financial_impact" variant="segmented">
                            <flux:radio value="1" label="Ya" />
                            <flux:radio value="0" label="Tidak" />
                        </flux:radio.group>
                        <flux:error name="financial_impact" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Kesesuaian dengan Turn-Around-Time Legal</flux:label>
                        <flux:radio.group wire:model="tat_legal_compliance" variant="segmented">
                            <flux:radio value="1" label="Ya" />
                            <flux:radio value="0" label="Tidak" />
                        </flux:radio.group>
                        <flux:error name="tat_legal_compliance" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Partner/Vendor</flux:label>
                        <flux:select wire:model="partner_id" required>
                            <option value="">Pilih Partner</option>
                            @foreach($this->partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->display_name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="partner_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Divisi</flux:label>
                        <flux:select wire:model.live="division_id" required>
                            <option value="">Pilih Divisi</option>
                            @foreach($this->divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="division_id" />
                    </flux:field>

                    @if($this->division_id)
                    <flux:field>
                        <flux:label>Departemen</flux:label>
                        <flux:select wire:model="department_id">
                            <option value="">Pilih Departemen (Opsional)</option>
                            @if($this->departments->isNotEmpty())
                                @foreach($this->departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            @else
                                <option value="" disabled>Belum ada departemen</option>
                            @endif
                        </flux:select>
                        <flux:error name="department_id" />
                    </flux:field>
                    @endif

                    <flux:field class="sm:col-span-2">
                        <flux:label>Jenis PIC</flux:label>
                        <flux:radio.group wire:model.live="pic_type" variant="segmented">
                            <flux:radio value="user" label="Pilih User Akun" />
                            <flux:radio value="manual" label="Input Manual" />
                        </flux:radio.group>
                    </flux:field>

                    @if($this->pic_type === 'user')
                    <flux:field class="sm:col-span-2">
                        <flux:label>PIC (Person In Charge)</flux:label>
                        <flux:select wire:model="pic_id" required>
                            <option value="">Pilih PIC</option>
                            @foreach($this->pics as $pic)
                            <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="pic_id" />
                    </flux:field>
                    @else
                    <flux:field>
                        <flux:label>Nama PIC</flux:label>
                        <flux:input wire:model="pic_name" placeholder="Nama Lengkap" required />
                        <flux:error name="pic_name" />
                    </flux:field>
                    
                    <flux:field>
                        <flux:label>Email PIC</flux:label>
                        <flux:input type="email" wire:model="pic_email" placeholder="email@example.com" required />
                        <flux:error name="pic_email" />
                    </flux:field>
                    @endif

                    <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status" required>
                            <option value="draft">Draft</option>
                            <option value="active">Aktif</option>
                            <option value="expired">Expired</option>
                            <option value="terminated">Terminated</option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field>

                    <!-- <flux:field>
                        <flux:label>Status</flux:label>
                        <flux:select wire:model="status" required>
                            <option value="draft">Draft</option>
                            <option value="active">Aktif</option>
                            <option value="expired">Expired</option>
                            <option value="terminated">Terminated</option>
                        </flux:select>
                        <flux:error name="status" />
                    </flux:field> -->

                    <flux:field>
                        <flux:label>Tanggal Mulai</flux:label>
                        <flux:input type="date" wire:model="start_date" required />
                        <flux:error name="start_date" />
                    </flux:field>

                    <div class="sm:col-span-2 space-y-3">
                        <flux:checkbox wire:model.live="is_auto_renewal" label="Auto Renewal" />
                        
                        @if(!$this->is_auto_renewal)
                        <flux:field>
                            <flux:label>Tanggal Berakhir</flux:label>
                            <flux:input type="date" wire:model="end_date" required />
                            <flux:error name="end_date" />
                        </flux:field>
                        @endif
                    </div>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Deskripsi</flux:label>
                        <flux:textarea wire:model="description" rows="3" />
                        <flux:error name="description" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Draft Usulan Dokumen (PDF/Word, max 10MB)</flux:label>
                        <input type="file" wire:model="draft_document" accept=".pdf,.doc,.docx" class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-green-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-green-700 hover:file:bg-green-100 dark:text-neutral-400 dark:file:bg-green-900/30 dark:file:text-green-400" />
                        <flux:error name="draft_document" />
                        <div wire:loading wire:target="draft_document" class="mt-2 text-sm text-neutral-500">
                            Mengupload draft...
                        </div>
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Dokumen Wajib - Multiple Files (Akta, KTP, dll, max 10MB each)</flux:label>
                        <input type="file" wire:model="mandatory_documents" multiple class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-purple-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-purple-700 hover:file:bg-purple-100 dark:text-neutral-400 dark:file:bg-purple-900/30 dark:file:text-purple-400" />
                        <flux:error name="mandatory_documents" />
                        <div wire:loading wire:target="mandatory_documents" class="mt-2 text-sm text-neutral-500">
                            Mengupload dokumen wajib...
                        </div>
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Legal Approval/Screenshot (PDF/Image, max 5MB)</flux:label>
                        <input type="file" wire:model="approval_document" accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-orange-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-orange-700 hover:file:bg-orange-100 dark:text-neutral-400 dark:file:bg-orange-900/30 dark:file:text-orange-400" />
                        <flux:error name="approval_document" />
                        <div wire:loading wire:target="approval_document" class="mt-2 text-sm text-neutral-500">
                            Mengupload approval...
                        </div>
                    </flux:field>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('contracts.show', $contract) }}" wire:navigate>
                    <flux:button variant="ghost">Batal</flux:button>
                </a>
                <flux:button type="submit" variant="primary">
                    Simpan Perubahan
                </flux:button>
            </div>
        </form>
    </div>
</div>
