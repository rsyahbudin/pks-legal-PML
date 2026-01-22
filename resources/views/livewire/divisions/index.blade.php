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
            ->withCount(['users', 'contracts', 'tickets'])
            ->orderBy('name')
            ->paginate(10);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'code', 'description', 'is_active']);
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
        $this->is_active = $division->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', $this->editingId ? "unique:divisions,code,{$this->editingId}" : 'unique:divisions,code'],
            'description' => ['nullable', 'string', 'max:500'],
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

    <!-- Filters -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center bg-white p-4 rounded-xl border border-neutral-200 dark:bg-zinc-900 dark:border-neutral-700 shadow-sm">
        <div class="flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari divisi..." icon="magnifying-glass" />
        </div>
        <!-- <div class="w-full sm:w-32">
             <flux:select wire:model.live="perPage">
                <option value="10">10 / page</option>
                <option value="25">25 / page</option>
                <option value="50">50 / page</option>
            </flux:select>
        </div> -->
    </div>

    <!-- Table -->
    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-neutral-600 dark:text-neutral-400">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-500 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-6 py-3 font-medium">Kode</th>
                        <th class="px-6 py-3 font-medium">Nama</th>
                        <th class="px-6 py-3 font-medium">Deskripsi</th>
                        <!-- <th class="px-6 py-3 font-medium text-center">Users</th> -->
                        <th class="px-6 py-3 font-medium text-center">Ticket</th>
                        <th class="px-6 py-3 font-medium text-center">Kontrak</th>
                        <th class="px-6 py-3 font-medium text-center">Status</th>
                        <th class="px-6 py-3 font-medium text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->divisions as $division)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="div-{{ $division->id }}">
                        <td class="px-6 py-4 font-mono text-sm font-medium text-neutral-900 dark:text-white">
                            {{ $division->code }}
                        </td>
                        <td class="px-6 py-4 font-medium text-neutral-900 dark:text-white">
                            {{ $division->name }}
                        </td>
                        <td class="px-6 py-4 truncate max-w-xs" title="{{ $division->description }}">
                            {{ Str::limit($division->description, 50) ?? '-' }}
                        </td>
                        <!-- <td class="px-6 py-4 text-center">
                            <flux:badge size="sm" color="zinc">{{ $division->users_count }}</flux:badge>
                        </td> -->
                        <td class="px-6 py-4 text-center">
                            <flux:badge size="sm" color="zinc">{{ $division->tickets_count }}</flux:badge>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <flux:badge size="sm" color="zinc">{{ $division->contracts_count }}</flux:badge>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($division->is_active)
                            <flux:badge size="sm" color="green" inset="top bottom">Aktif</flux:badge>
                            @else
                            <flux:badge size="sm" color="zinc" inset="top bottom">Nonaktif</flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-end">
                            <flux:dropdown>
                                <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" />
                                <flux:menu>
                                    @if(auth()->user()?->hasPermission('divisions.manage'))
                                    <flux:menu.item icon="pencil" wire:click="edit({{ $division->id }})">Edit</flux:menu.item>
                                    <flux:menu.item icon="squares-plus" wire:click="$dispatch('manage-sub-divisions', { divisionId: {{ $division->id }} })">Kelola Dept</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" wire:click="delete({{ $division->id }})" wire:confirm="Apakah Anda yakin?" variant="danger">Hapus</flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-neutral-500">
                            Belum ada divisi
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-neutral-200 px-6 py-4 dark:border-neutral-700">
            <div class="flex flex-col items-center justify-between gap-4 sm:flex-row">
                <p class="text-sm text-neutral-500 dark:text-neutral-400">
                    Showing <span class="font-medium">{{ $this->divisions->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $this->divisions->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ $this->divisions->total() }}</span> results
                </p>
                <div class="w-full sm:w-auto">
                     {{ $this->divisions->links() }}
                </div>
            </div>
        </div>
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
