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
    public string $email_reminder_subject = '';
    public string $email_reminder_body = '';

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
        $this->email_reminder_subject = Setting::get('email_reminder_subject', 'Agreement [XX] – Expiration Reminder');
        $this->email_reminder_body = Setting::get('email_reminder_body', '');
    }

    public function save(): void
    {
        if (!auth()->user()?->hasPermission('settings.manage')) {
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
            'email_reminder_subject' => ['required', 'string', 'max:255'],
            'email_reminder_body' => ['required', 'string', 'max:2000'],
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
        Setting::set('email_reminder_subject', $this->email_reminder_subject, 'text');
        Setting::set('email_reminder_body', $this->email_reminder_body, 'text');

        session()->flash('success', 'Pengaturan berhasil disimpan.');
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
            </div>
        </div>

        <!-- Email Template Settings -->
        @if(auth()->user()?->hasPermission('email_templates.edit'))
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Template Email Reminder</h2>
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Subject Email</flux:label>
                    <flux:input wire:model="email_reminder_subject" placeholder="Agreement [XX] – Expiration Reminder" required />
                    <flux:description>Gunakan [XX] untuk nomor kontrak</flux:description>
                    <flux:error name="email_reminder_subject" />
                </flux:field>
                
                <flux:field>
                    <flux:label>Isi Email</flux:label>
                    <flux:textarea wire:model="email_reminder_body" rows="10" required />
                    <flux:description>
                        Placeholder yang tersedia: [XX] = Nomor Kontrak, [expiration date] = Tanggal Berakhir
                    </flux:description>
                    <flux:error name="email_reminder_body" />
                </flux:field>
            </div>
        </div>
        @endif

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Simpan Pengaturan</flux:button>
        </div>
    </form>
</div>
