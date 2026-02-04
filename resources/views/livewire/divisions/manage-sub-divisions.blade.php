<?php

use Livewire\Volt\Component;
use App\Models\Division;
use App\Models\Department;
use Livewire\Attributes\On;

new class extends Component {
    public ?Division $division = null;
    public $departments = [];
    
    public $name = '';
    public $code = '';
    public $email = '';
    public $cc_emails = '';
    public $editingId = null;
    public $showModal = false;

    #[On('manage-sub-divisions')]
    public function open($divisionId)
    {
        $this->division = Division::find($divisionId);
        $this->refreshList();
        $this->showModal = true;
        // Open the modal using Flux/Alpine logic if needed, or binding
        // Flux modals usually open via name. <flux:modal name="sub-divisions-modal" wire:model.self="showModal">
    }

    public function refreshList()
    {
        if ($this->division) {
            $this->departments = $this->division->departments()->orderBy('name')->get();
        } else {
            $this->departments = [];
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'cc_emails' => 'nullable|string',
        ]);

        if ($this->editingId) {
            Department::find($this->editingId)->update([
                'name' => $this->name,
                'code' => $this->code,
                'email' => $this->email,
                'cc_emails' => $this->cc_emails,
            ]);
        } else {
            $this->division->departments()->create([
                'name' => $this->name,
                'code' => $this->code,
                'email' => $this->email,
                'cc_emails' => $this->cc_emails,
            ]);
        }

        $this->resetForm();
        $this->refreshList();
    }

    public function edit($id)
    {
        $dept = Department::find($id);
        $this->editingId = $dept->id;
        $this->name = $dept->name;
        $this->code = $dept->code;
        $this->email = $dept->email ?? '';
        $this->cc_emails = $dept->cc_emails;
    }

    public function delete($id)
    {
        Department::find($id)->delete();
        $this->refreshList();
    }
    
    public function resetForm()
    {
        $this->reset(['name', 'code', 'email', 'cc_emails', 'editingId']);
    }

    public function close()
    {
        $this->showModal = false;
        $this->resetForm();
    }
}; ?>

<flux:modal name="sub-divisions-modal" class="min-h-[500px] md:w-[600px]" wire:model="showModal">
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-bold">Kelola Departemen</h2>
            @if($division)
            <p class="text-sm text-neutral-500">{{ $division->name }}</p>
            @endif
        </div>

        <!-- Form -->
        <div class="rounded-xl border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-800">
            <form wire:submit="save" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Nama Departemen</flux:label>
                        <flux:input wire:model="name" placeholder="Contoh: IT Support" required />
                        <flux:error name="name" />
                    </flux:field>
                    
                    <flux:field>
                        <flux:label>Kode</flux:label>
                        <flux:input wire:model="code" placeholder="ITS" />
                        <flux:error name="code" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Email Departemen</flux:label>
                    <flux:input wire:model="email" type="email" placeholder="department@example.com" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>CC Emails (untuk reminder)</flux:label>
                    <flux:textarea wire:model="cc_emails" rows="2" placeholder="email1@example.com, email2@example.com" />
                    <flux:description>Pisahkan dengan koma.</flux:description>
                    <flux:error name="cc_emails" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    @if($editingId)
                    <flux:button wire:click="resetForm" size="sm" variant="ghost">Batal</flux:button>
                    @endif
                    <flux:button type="submit" size="sm" variant="primary">{{ $editingId ? 'Update' : 'Tambah' }}</flux:button>
                </div>
            </form>
        </div>

        <!-- List -->
        <div class="space-y-2">
            <h3 class="font-medium">Daftar Departemen</h3>
            @if(count($departments) > 0)
            <div class="divide-y divide-neutral-200 rounded-lg border border-neutral-200 bg-white dark:divide-neutral-700 dark:border-neutral-700 dark:bg-zinc-900">
                @foreach($departments as $dept)
                <div class="flex items-center justify-between p-3">
                    <div>
                        <div class="font-medium">{{ $dept->name }} <span class="text-xs text-neutral-500">({{ $dept->code ?? '-' }}) | {{ $dept->email }}</span></div>
                        @if($dept->cc_emails)
                        <div class="text-xs text-neutral-500">{{ Str::limit($dept->cc_emails, 50) }}</div>
                        @endif
                    </div>
                    <div class="flex gap-2">
                        <flux:button icon="pencil" size="xs" variant="ghost" wire:click="edit({{ $dept->id }})" />
                        <flux:button icon="trash" size="xs" variant="danger" wire:click="delete({{ $dept->id }})" wire:confirm="Hapus departemen ini?" />
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div class="p-4 text-center text-sm text-neutral-500">Belum ada departemen.</div>
            @endif
        </div>
    </div>
</flux:modal>
