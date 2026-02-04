<?php

use App\Models\Department;
use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public bool $showModal = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $user_id = '';
    public string $role_id = '';
    public string $division_id = '';
    public string $department_id = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function getUsersProperty()
    {
        return User::with(['role', 'division', 'department'])
            ->when($this->search, fn($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(10);
    }

    public function getRolesProperty()
    {
        return Role::orderBy('name')->get();
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

    public function create(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'user_id', 'role_id', 'division_id', 'department_id']);
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->user_id = $user->user_id ?? '';
        $this->role_id = (string) ($user->role_id ?? '');
        $this->division_id = (string) ($user->division_id ?? '');
        $this->department_id = (string) ($user->department_id ?? '');
        $this->showModal = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', $this->editingId ? "unique:users,email,{$this->editingId}" : 'unique:users,email'],
            'user_id' => ['nullable', 'string', 'max:10', 'regex:/^[0-9]*$/'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'division_id' => ['nullable', 'exists:divisions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
        ];

        if (!$this->editingId || $this->password) {
            $rules['password'] = ['required', 'min:8'];
        }

        $validated = $this->validate($rules);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'user_id' => $validated['user_id'] ?: null,
            'role_id' => $validated['role_id'] ?: null,
            'division_id' => $validated['division_id'] ?: null,
            'department_id' => $validated['department_id'] ?: null,
        ];

        if (!empty($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->editingId) {
            User::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Pengguna berhasil diperbarui.');
        } else {
            $data['email_verified_at'] = now();
            User::create($data);
            session()->flash('success', 'Pengguna berhasil ditambahkan.');
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        if ($id === auth()->id()) {
            session()->flash('error', 'Tidak dapat menghapus akun sendiri.');
            return;
        }
        User::findOrFail($id)->delete();
        session()->flash('success', 'Pengguna berhasil dihapus.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Pengguna</h1>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Kelola pengguna sistem</p>
        </div>
        @if(auth()->user()?->hasPermission('users.manage'))
        <flux:button variant="primary" icon="plus" wire:click="create">Tambah Pengguna</flux:button>
        @endif
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-zinc-900">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="Cari nama atau email..." icon="magnifying-glass" />
    </div>

    <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600 dark:bg-zinc-800 dark:text-neutral-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Nama</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">NIK</th>
                        <th class="px-4 py-3 text-left">Role</th>
                        <th class="px-4 py-3 text-left">Divisi</th>
                        <th class="px-4 py-3 text-left">Departemen</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    @forelse($this->users as $user)
                    <tr class="hover:bg-neutral-50 dark:hover:bg-zinc-800" wire:key="user-{{ $user->id }}">
                        <td class="px-4 py-3 font-medium text-neutral-900 dark:text-white">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->user_id ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($user->role)
                            <flux:badge color="{{ $user->role->slug === 'super-admin' ? 'red' : ($user->role->slug === 'legal' ? 'blue' : 'zinc') }}">{{ $user->role->name }}</flux:badge>
                            @else
                            <span class="text-neutral-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->division?->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600 dark:text-neutral-300">{{ $user->department?->name ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @if(auth()->user()?->hasPermission('users.manage'))
                                <flux:button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $user->id }})" />
                                @if($user->id !== auth()->id())
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="delete({{ $user->id }})" wire:confirm="Apakah Anda yakin?" />
                                @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-neutral-500">Belum ada pengguna</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->users->hasPages())
        <div class="border-t border-neutral-200 px-4 py-3 dark:border-neutral-700">{{ $this->users->links() }}</div>
        @endif
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ $editingId ? 'Edit Pengguna' : 'Tambah Pengguna' }}</flux:heading>
            
            <flux:field>
                <flux:label>Nama</flux:label>
                <flux:input wire:model="name" required />
                <flux:error name="name" />
            </flux:field>
            
            <flux:field>
                <flux:label>Email</flux:label>
                <flux:input type="email" wire:model="email" required />
                <flux:error name="email" />
            </flux:field>
            
            <flux:field>
                <flux:label>NIK / User ID</flux:label>
                <flux:input 
                    type="text" 
                    wire:model="user_id" 
                    maxlength="10"
                    pattern="[0-9]*"
                    inputmode="numeric"
                    placeholder="Enter 10-digit NIK"
                />
                <flux:error name="user_id" />
            </flux:field>
            
            <flux:field>
                <flux:label>Password {{ $editingId ? '(kosongkan jika tidak diubah)' : '' }}</flux:label>
                <flux:input type="password" wire:model="password" :required="!$editingId" />
                <flux:error name="password" />
            </flux:field>
            
            <flux:field>
                <flux:label>Role</flux:label>
                <flux:select wire:model="role_id">
                    <option value="">Pilih Role</option>
                    @foreach($this->roles as $role)
                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </flux:select>
                <flux:error name="role_id" />
            </flux:field>
            
            <flux:field>
                <flux:label>Divisi</flux:label>
                <flux:select wire:model.live="division_id">
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
            
            <div class="flex justify-end gap-3 pt-4">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">Batal</flux:button>
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
