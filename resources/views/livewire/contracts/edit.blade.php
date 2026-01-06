<?php

use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Division;
use App\Models\Partner;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    public Contract $contract;

    public string $contract_number = '';
    public string $partner_id = '';
    public string $division_id = '';
    public string $pic_type = 'user'; // 'user' or 'manual'
    public string $pic_id = '';
    public string $pic_name = '';
    public string $pic_email = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $description = '';
    public string $status = 'draft';
    public $document;

    public function mount(Contract $contract): void
    {
        $this->contract = $contract;
        $this->contract_number = $contract->contract_number;
        $this->partner_id = (string) $contract->partner_id;
        $this->division_id = (string) $contract->division_id;
        
        if ($contract->pic_id) {
            $this->pic_type = 'user';
            $this->pic_id = (string) $contract->pic_id;
        } else {
            $this->pic_type = 'manual';
            $this->pic_name = $contract->pic_name ?? '';
            $this->pic_email = $contract->pic_email ?? '';
        }
        
        $this->start_date = $contract->start_date->format('Y-m-d');
        $this->end_date = $contract->end_date->format('Y-m-d');
        $this->description = $contract->description ?? '';
        $this->status = $contract->status;
    }

    public function getPartnersProperty()
    {
        return Partner::active()->orderBy('name')->get();
    }

    public function getDivisionsProperty()
    {
        return Division::active()->orderBy('name')->get();
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
            'partner_id' => ['required', 'exists:partners,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'active', 'expired', 'terminated'])],
            'document' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
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

        $documentPath = $this->contract->document_path;
        if ($this->document) {
            $documentPath = $this->document->store('contracts', 'public');
        }

        $this->contract->update([
            'contract_number' => $validated['contract_number'],
            'partner_id' => $validated['partner_id'],
            'division_id' => $validated['division_id'],
            'pic_id' => $this->pic_type === 'user' ? $validated['pic_id'] : null,
            'pic_name' => $this->pic_type === 'manual' ? $validated['pic_name'] : null,
            'pic_email' => $this->pic_type === 'manual' ? $validated['pic_email'] : null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'document_path' => $documentPath,
        ]);

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
                    <flux:field class="sm:col-span-2">
                        <flux:label>Nomor Kontrak</flux:label>
                        <flux:input wire:model="contract_number" required />
                        <flux:error name="contract_number" />
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
                        <flux:select wire:model="division_id" required>
                            <option value="">Pilih Divisi</option>
                            @foreach($this->divisions as $division)
                            <option value="{{ $division->id }}">{{ $division->name }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="division_id" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Jenis PIC</flux:label>
                        <flux:radio.group wire:model.live="pic_type" variant="segmented">
                            <flux:radio value="user" label="Pilih User Akun" />
                            <flux:radio value="manual" label="Input Manual" />
                        </flux:radio.group>
                    </flux:field>

                    @if($this->pic_type === 'user')
                    <flux:field>
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

                    <flux:field>
                        <flux:label>Tanggal Mulai</flux:label>
                        <flux:input type="date" wire:model="start_date" required />
                        <flux:error name="start_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Tanggal Berakhir</flux:label>
                        <flux:input type="date" wire:model="end_date" required />
                        <flux:error name="end_date" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Deskripsi</flux:label>
                        <flux:textarea wire:model="description" rows="3" />
                        <flux:error name="description" />
                    </flux:field>

                    <flux:field class="sm:col-span-2">
                        <flux:label>Dokumen Kontrak (PDF/Word, max 10MB)</flux:label>
                        @if($contract->document_path)
                        <div class="mb-2 flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-300">
                            <flux:icon name="document" class="h-4 w-4" />
                            Dokumen saat ini tersedia. Upload file baru untuk mengganti.
                        </div>
                        @endif
                        <input type="file" wire:model="document" accept=".pdf,.doc,.docx" class="block w-full text-sm text-neutral-500 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:text-neutral-400 dark:file:bg-blue-900/30 dark:file:text-blue-400" />
                        <flux:error name="document" />
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
