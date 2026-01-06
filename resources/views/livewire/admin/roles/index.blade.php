<?php

use App\Models\Permission;
use App\Models\Role;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;
    public bool $showPermissionModal = false;
    public ?Role $editingRole = null;
    public array $selectedPermissions = [];

    public string $name = '';
    public string $slug = '';
    public string $description = '';

    public function getRolesProperty()
    {
        return Role::withCount('permissions')
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    public function getPermissionsGroupedProperty()
    {
        return Permission::allGrouped();
    }

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'description']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $role = Role::findOrFail($id);
        $this->editingId = $role->id;
        $this->name = $role->name;
        $this->slug = $role->slug;
        $this->description = $role->description ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:50', $this->editingId ? "unique:roles,slug,{$this->editingId}" : 'unique:roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($this->editingId) {
            Role::findOrFail($this->editingId)->update($validated);
            session()->flash('success', 'Role berhasil diperbarui.');
        } else {
            Role::create($validated);
            session()->flash('success', 'Role berhasil ditambahkan.');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        $role = Role::findOrFail($id);
        if ($role->is_system) {
            session()->flash('error', 'Role sistem tidak dapat dihapus.');
            return;
        }
        $role->delete();
        session()->flash('success', 'Role berhasil dihapus.');
    }

    public function editPermissions(int $id): void
    {
        $this->editingRole = Role::with('permissions')->findOrFail($id);
        $this->selectedPermissions = $this->editingRole->permissions->pluck('id')->toArray();
        $this->showPermissionModal = true;
    }

    public function savePermissions(): void
    {
        if ($this->editingRole) {
            $this->editingRole->syncPermissions($this->selectedPermissions);
            session()->flash('success', 'Permissions berhasil diperbarui.');
        }
        $this->showPermissionModal = false;
        $this->editingRole = null;
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Role & Permission</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Kelola role dan akses permission</p>
        </div>
        @if(auth()->user()?->hasPermission('roles.manage'))
        <flux:button variant="primary" icon="plus" wire:click="create">Tambah Role</flux:button>
        @endif
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari role..." icon="magnifying-glass" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($this->roles as $role)
        <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900" wire:key="role-{{ $role->id }}">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="font-semibold text-neutral-900 dark:text-white">{{ $role->name }}</h3>
                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $role->slug }}</p>
                </div>
                @if($role->is_system)
                <flux:badge color="amber">System</flux:badge>
                @endif
            </div>
            @if($role->description)
            <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-300">{{ $role->description }}</p>
            @endif
            <div class="mt-3 flex items-center justify-between">
                <span class="text-sm text-neutral-500">{{ $role->permissions_count }} permissions</span>
                @if(auth()->user()?->hasPermission('roles.manage'))
                <div class="flex gap-2">
                    <flux:button size="sm" variant="ghost" icon="shield-check" wire:click="editPermissions({{ $role->id }})" title="Edit Permissions" />
                    <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $role->id }})" />
                    @if(!$role->is_system)
                    <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $role->id }})" wire:confirm="Apakah Anda yakin?" />
                    @endif
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <!-- Role Modal -->
    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? 'Edit Role' : 'Tambah Role' }}</flux:heading>
            <flux:field>
                <flux:label>Nama</flux:label>
                <flux:input wire:model="name" required />
            </flux:field>
            <flux:field>
                <flux:label>Slug</flux:label>
                <flux:input wire:model="slug" placeholder="contoh: manager" required />
                <flux:description>Identifier unik, gunakan huruf kecil dan strip</flux:description>
            </flux:field>
            <flux:field>
                <flux:label>Deskripsi</flux:label>
                <flux:textarea wire:model="description" rows="2" />
            </flux:field>
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>

    <!-- Permission Modal -->
    <flux:modal wire:model="showPermissionModal" class="w-full max-w-2xl">
        <div class="space-y-4">
            <flux:heading>Edit Permissions: {{ $editingRole?->name }}</flux:heading>
            <div class="max-h-96 overflow-y-auto space-y-4">
                @foreach($this->permissionsGrouped as $group => $permissions)
                <div class="rounded-lg border border-neutral-200 p-3 dark:border-neutral-700">
                    <h4 class="mb-2 font-medium capitalize text-neutral-900 dark:text-white">{{ $group }}</h4>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($permissions as $permission)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" value="{{ $permission->id }}" wire:model="selectedPermissions" class="rounded border-neutral-300 text-blue-600 focus:ring-blue-500">
                            {{ $permission->name }}
                        </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showPermissionModal', false)">Batal</flux:button>
                <flux:button type="button" variant="primary" wire:click="savePermissions">Simpan Permissions</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
