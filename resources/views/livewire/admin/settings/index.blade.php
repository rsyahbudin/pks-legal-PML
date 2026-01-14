<?php

use App\Models\Setting;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('components.layouts.app')] class extends Component {
    public int $reminder_threshold_warning = 60;
    public int $reminder_threshold_critical = 30;
    public bool $reminder_email_enabled = true;
    public bool $reminder_email_pic = true;
    public bool $reminder_email_legal = true;
    public bool $reminder_email_managers = false;
    public string $legal_team_email = '';
    public string $reminder_send_time = '08:00';
    public string $app_name = '';
    public string $company_name = '';
    public array $reminder_days = [60, 30, 7];


    public function mount(): void
    {
        $this->reminder_threshold_warning = (int) Setting::get('reminder_threshold_warning', 60);
        $this->reminder_threshold_critical = (int) Setting::get('reminder_threshold_critical', 30);
        $this->reminder_email_enabled = (bool) Setting::get('reminder_email_enabled', true);
        $this->reminder_email_pic = (bool) Setting::get('reminder_email_pic', true);
        $this->reminder_email_legal = (bool) Setting::get('reminder_email_legal', true);
        $this->reminder_email_managers = (bool) Setting::get('reminder_email_managers', false);
        $this->legal_team_email = Setting::get('legal_team_email', '');
        $this->reminder_send_time = Setting::get('reminder_send_time', '08:00');
        $this->app_name = Setting::get('app_name', 'PKS Tracking System');
        $this->company_name = Setting::get('company_name', '');
        
        $reminderDays = Setting::get('reminder_days', [60, 30, 7]);
        $this->reminder_days = is_array($reminderDays) ? $reminderDays : json_decode($reminderDays, true) ?? [60, 30, 7];

    }

    public function save(): void
    {
        if (!auth()->user()?->hasPermission('settings.edit')) {
            session()->flash('error', 'Anda tidak memiliki akses untuk mengubah pengaturan.');
            return;
        }

        $this->validate([
            'reminder_threshold_warning' => ['required', 'integer', 'min:1', 'max:365'],
            'reminder_threshold_critical' => ['required', 'integer', 'min:1', 'max:365', 'lt:reminder_threshold_warning'],
            'legal_team_email' => ['nullable', 'email'],
            'reminder_send_time' => ['required', 'date_format:H:i'],
            'app_name' => ['required', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:100'],
            'reminder_days' => ['required', 'array', 'min:1'],
            'reminder_days.*' => ['required', 'integer', 'min:1', 'max:365'],

        ]);

        Setting::set('reminder_threshold_warning', $this->reminder_threshold_warning, 'integer');
        Setting::set('reminder_threshold_critical', $this->reminder_threshold_critical, 'integer');
        Setting::set('reminder_email_enabled', $this->reminder_email_enabled, 'boolean');
        Setting::set('reminder_email_pic', $this->reminder_email_pic, 'boolean');
        Setting::set('reminder_email_legal', $this->reminder_email_legal, 'boolean');
        Setting::set('reminder_email_managers', $this->reminder_email_managers, 'boolean');
        Setting::set('legal_team_email', $this->legal_team_email, 'string');
        Setting::set('reminder_send_time', $this->reminder_send_time, 'string');
        Setting::set('app_name', $this->app_name, 'string');
        Setting::set('company_name', $this->company_name, 'string');
        Setting::set('reminder_days', json_encode(array_values($this->reminder_days)), 'string');


        session()->flash('success', 'Pengaturan berhasil disimpan.');
    }

    public function addReminderDay(): void
    {
        $this->reminder_days[] = 15; // Default new day
    }

    public function removeReminderDay(int $index): void
    {
        unset($this->reminder_days[$index]);
        $this->reminder_days = array_values($this->reminder_days); // Re-index
    }
}; ?>

<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">Pengaturan Sistem</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Konfigurasi threshold reminder dan email</p>
    </div>

    @if(session('success'))
    <div class="rounded-lg bg-green-50 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-200">
        {{ session('success') }}
    </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <!-- General Settings -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Umum</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Nama Aplikasi</flux:label>
                    <flux:input wire:model="app_name" required />
                </flux:field>
                <flux:field>
                    <flux:label>Nama Perusahaan</flux:label>
                    <flux:input wire:model="company_name" />
                </flux:field>
            </div>
        </div>

        <!-- Threshold Settings -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Traffic Light Threshold</h2>
            <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">
                Atur batas hari untuk menentukan warna status kontrak
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>🟡 Warning (Kuning) - Hari</flux:label>
                    <flux:input type="number" wire:model="reminder_threshold_warning" min="1" max="365" required />
                    <flux:description>Kontrak dengan sisa <= hari ini akan ditampilkan kuning</flux:description>
                </flux:field>
                <flux:field>
                    <flux:label>🔴 Critical (Merah) - Hari</flux:label>
                    <flux:input type="number" wire:model="reminder_threshold_critical" min="1" max="365" required />
                    <flux:description>Kontrak dengan sisa <= hari ini akan ditampilkan merah</flux:description>
                </flux:field>
            </div>
        </div>

        <!-- Email Settings -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Email Reminder</h2>
            <div class="space-y-4">
                <flux:switch wire:model="reminder_email_enabled" label="Aktifkan email reminder otomatis" />
                
                <div class="ml-6 space-y-3 {{ !$reminder_email_enabled ? 'opacity-50' : '' }}">
                    <flux:switch wire:model="reminder_email_pic" label="Kirim ke PIC kontrak" />
                    <flux:switch wire:model="reminder_email_legal" label="Kirim ke tim Legal" />
                    <flux:switch wire:model="reminder_email_managers" label="Kirim ke Management" />
                </div>

                <div class="grid gap-4 pt-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>Email Tim Legal</flux:label>
                        <flux:input type="email" wire:model="legal_team_email" placeholder="legal@company.com" />
                    </flux:field>
                    <flux:field>
                        <flux:label>Waktu Kirim Harian</flux:label>
                        <flux:input type="time" wire:model="reminder_send_time" />
                    </flux:field>
                </div>

                <flux:field class="pt-4">
                    <flux:label>Reminder Days (H- hari sebelum berakhir)</flux:label>
                    <div class="flex flex-wrap gap-2">
                        @foreach($reminder_days as $index => $day)
                            <div class="flex items-center gap-1">
                                <flux:input 
                                    type="number" 
                                    wire:model="reminder_days.{{ $index }}" 
                                    min="1" 
                                    max="365"
                                    class="w-20"
                                />
                                @if(count($reminder_days) > 1)
                                    <flux:button type="button" wire:click="removeReminderDay({{ $index }})" variant="danger" size="sm">×</flux:button>
                                @endif
                            </div>
                        @endforeach
                        <flux:button type="button" wire:click="addReminderDay" variant="ghost" size="sm">+ Tambah</flux:button>
                    </div>
                    <flux:description>
                        Email reminder otomatis akan dikirim tepat pada H- hari ini. Default: 60, 30, 7 hari sebelum contract berakhir.
                    </flux:description>
                    <flux:error name="reminder_days" />
                </flux:field>
            </div>
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Simpan Pengaturan</flux:button>
        </div>
    </form>
</div>
