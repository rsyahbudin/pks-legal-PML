<?php

use App\Models\Division;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $code = '';
    public string $description = '';
    public string $cc_emails = '';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getDivisionsProperty()
    {
        return Division::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%"))
            ->withCount(['users', 'contracts'])
            ->orderBy('name')
            ->paginate(10);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description', 'cc_emails', 'is_active']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $division = Division::findOrFail($id);
        $this->editingId = $division->id;
        $this->name = $division->name;
        $this->code = $division->code;
        $this->description = $division->description ?? '';
        $this->cc_emails = $division->cc_emails ?? '';
        $this->is_active = $division->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', $this->editingId ? "unique:divisions,code,{$this->editingId}" : 'unique:divisions,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'cc_emails' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            Division::findOrFail($this->editingId)->update($validated);
            session()->flash('success', 'Divisi berhasil diperbarui.');
        } else {
            Division::create($validated);
            session()->flash('success', 'Divisi berhasil ditambahkan.');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        if (!auth()->user()?->hasPermission('divisions.manage')) {
            return;
        }
        Division::findOrFail($id)->delete();
        session()->flash('success', 'Divisi berhasil dihapus.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Divisi</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Kelola daftar divisi internal</p>
        </div>
        @if(auth()->user()?->hasPermission('divisions.manage'))
        <flux:button variant="primary" icon="plus" wire:click="create">Tambah Divisi</flux:button>
        @endif
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari divisi..." icon="magnifying-glass" />
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Kode</th>
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Deskripsi</th>
                        <th class="px-4 py-3 text-center">Users</th>
                        <th class="px-4 py-3 text-center">Kontrak</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->divisions as $division)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="div-{{ $division->id }}">
                        <td class="px-4 py-3 font-mono text-sm font-medium text-neutral-900 dark:text-white">{{ $division->code }}</td>
                        <td class="px-4 py-3 font-medium text-neutral-900 dark:text-white">{{ $division->name }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ Str::limit($division->description, 50) ?? '-' }}</td>
                        <td class="px-4 py-3 text-center"><flux:badge>{{ $division->users_count }}</flux:badge></td>
                        <td class="px-4 py-3 text-center"><flux:badge>{{ $division->contracts_count }}</flux:badge></td>
                        <td class="px-4 py-3 text-center">
                            @if($division->is_active)
                            <flux:badge color="green">Aktif</flux:badge>
                            @else
                            <flux:badge color="zinc">Nonaktif</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @if(auth()->user()?->hasPermission('divisions.manage'))
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $division->id }})" />
                                <flux:button size="sm" variant="ghost" icon="squares-plus" wire:click="$dispatch('manage-sub-divisions', { divisionId: {{ $division->id }} })" tooltip="Kelola Departemen" />
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $division->id }})" wire:confirm="Apakah Anda yakin?" />
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-neutral-500">Belum ada divisi</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->divisions->hasPages())
        <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">{{ $this->divisions->links() }}</div>
        @endif
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? 'Edit Divisi' : 'Tambah Divisi' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Kode</flux:label>
                    <flux:input wire:model="code" placeholder="IT" required />
                    <flux:error name="code" />
                </flux:field>
                <flux:field>
                    <flux:label>Nama</flux:label>
                    <flux:input wire:model="name" required />
                    <flux:error name="name" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:textarea wire:model="description" rows="2" />
            </flux:field>

            <flux:switch wire:model="is_active" label="Aktif" />
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
    
    <livewire:divisions.manage-sub-divisions />
</div>
