<?php

use App\Models\Partner;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $company_name = '';
    public string $email = '';
    public string $phone = '';
    public string $address = '';
    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getPartnersProperty()
    {
        return Partner::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('company_name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->withCount('contracts')
            ->orderBy('name')
            ->paginate(10);
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'company_name', 'email', 'phone', 'address', 'is_active']);
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $partner = Partner::findOrFail($id);
        $this->editingId = $partner->id;
        $this->name = $partner->name;
        $this->company_name = $partner->company_name ?? '';
        $this->email = $partner->email ?? '';
        $this->phone = $partner->phone ?? '';
        $this->address = $partner->address ?? '';
        $this->is_active = $partner->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        if ($this->editingId) {
            Partner::findOrFail($this->editingId)->update($validated);
            session()->flash('success', 'Partner berhasil diperbarui.');
        } else {
            Partner::create($validated);
            session()->flash('success', 'Partner berhasil ditambahkan.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'company_name', 'email', 'phone', 'address', 'is_active']);
    }

    public function delete(int $id): void
    {
        if (!auth()->user()?->hasPermission('partners.delete')) {
            return;
        }
        Partner::findOrFail($id)->delete();
        session()->flash('success', 'Partner berhasil dihapus.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Partner / Vendor</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Kelola daftar partner dan vendor</p>
        </div>
        @if(auth()->user()?->hasPermission('partners.create'))
        <flux:button variant="primary" icon="plus" wire:click="create">
            Tambah Partner
        </flux:button>
        @endif
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama, perusahaan, atau email..." icon="magnifying-glass" />
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Perusahaan</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Telepon</th>
                        <th class="px-4 py-3 text-center">Kontrak</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->partners as $partner)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="partner-{{ $partner->id }}">
                        <td class="px-4 py-3 font-medium text-neutral-900 dark:text-white">{{ $partner->name }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $partner->company_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $partner->email ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $partner->phone ?? '-' }}</td>
                        <td class="px-4 py-3 text-center">
                            <flux:badge>{{ $partner->contracts_count }}</flux:badge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($partner->is_active)
                            <flux:badge color="green">Aktif</flux:badge>
                            @else
                            <flux:badge color="zinc">Nonaktif</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @if(auth()->user()?->hasPermission('partners.edit'))
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $partner->id }})" />
                                @endif
                                @if(auth()->user()?->hasPermission('partners.delete'))
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $partner->id }})" wire:confirm="Apakah Anda yakin ingin menghapus partner ini?" />
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-neutral-500">Belum ada partner</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->partners->hasPages())
        <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">
            {{ $this->partners->links() }}
        </div>
        @endif
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? 'Edit Partner' : 'Tambah Partner' }}</flux:heading>

            <flux:field>
                <flux:label>Nama Kontak</flux:label>
                <flux:input wire:model="name" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Nama Perusahaan</flux:label>
                <flux:input wire:model="company_name" />
                <flux:error name="company_name" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="email" />
                    <flux:error name="email" />
                </flux:field>

                <flux:field>
                    <flux:label>Telepon</flux:label>
                    <flux:input wire:model="phone" />
                    <flux:error name="phone" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Alamat</flux:label>
                <flux:textarea wire:model="address" rows="2" />
                <flux:error name="address" />
            </flux:field>

            <flux:field>
                <flux:switch wire:model="is_active" label="Aktif" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
