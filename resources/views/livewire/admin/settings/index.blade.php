<?php

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component
{
    public bool $reminder_email_enabled = true;

    public string $reminder_send_time = '08:00';

    public string $ticket_cutoff_time = '17:00';

    public string $app_name = '';

    public string $company_name = '';

    public array $reminder_days = [60, 30, 7];

    public function mount(): void
    {
        $this->reminder_email_enabled = (bool) Setting::get('reminder_email_enabled', true);
        $this->reminder_send_time = Setting::get('reminder_send_time', '08:00');
        $this->ticket_cutoff_time = Setting::get('ticket_cutoff_time', '17:00');
        $this->app_name = Setting::get('app_name', 'PKS Tracking System');
        $this->company_name = Setting::get('company_name', '');

        $reminderDays = Setting::get('reminder_days', [60, 30, 7]);
        $this->reminder_days = is_array($reminderDays) ? $reminderDays : json_decode($reminderDays, true) ?? [60, 30, 7];

    }

    public function save(): void
    {
        if (! auth()->user()?->hasPermission('settings.edit')) {
            session()->flash('error', 'You do not have access to modify settings.');

            return;
        }

        $this->validate([
            'reminder_send_time' => ['required', 'date_format:H:i'],
            'ticket_cutoff_time' => ['required', 'date_format:H:i'],
            'app_name' => ['required', 'string', 'max:100'],
            'company_name' => ['nullable', 'string', 'max:100'],
            'reminder_days' => ['required', 'array', 'min:1'],
            'reminder_days.*' => ['required', 'integer', 'min:1', 'max:365'],

        ]);

        Setting::set('reminder_email_enabled', $this->reminder_email_enabled, 'boolean');
        Setting::set('reminder_send_time', $this->reminder_send_time, 'string');
        Setting::set('ticket_cutoff_time', $this->ticket_cutoff_time, 'string');
        Setting::set('app_name', $this->app_name, 'string');
        Setting::set('company_name', $this->company_name, 'string');
        Setting::set('reminder_days', json_encode(array_values($this->reminder_days)), 'string');

        session()->flash('success', 'Settings saved successfully.');
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
        <h1 class="text-2xl font-bold text-neutral-900 dark:text-white">System Settings</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">Configure reminder thresholds and email settings</p>
    </div>

    @if(session('success'))
    <div class="rounded-lg bg-green-50 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-200">
        {{ session('success') }}
    </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <!-- General Settings -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">General</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Application Name</flux:label>
                    <flux:input wire:model="app_name" required />
                </flux:field>
                <flux:field>
                    <flux:label>Company Name</flux:label>
                    <flux:input wire:model="company_name" />
                </flux:field>
            </div>
        </div>

        <!-- Email Reminder Settings -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Email Reminder</h2>
            <div class="space-y-4">
                <flux:switch wire:model="reminder_email_enabled" label="Enable automatic email reminders" />
                
                <div class="grid gap-4 pt-4">
                    <flux:field>
                        <flux:label>Daily Send Time</flux:label>
                        <flux:input type="time" wire:model="reminder_send_time" />
                        <flux:description>Reminder emails will be sent automatically at this time every day. Emails will be sent to the Legal Department email.</flux:description>
                    </flux:field>
                </div>

                <flux:field class="pt-4">
                    <flux:label>Reminder Days (days before expiration)</flux:label>
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
                        <flux:button type="button" wire:click="addReminderDay" variant="ghost" size="sm">+ Add</flux:button>
                    </div>
                    <flux:description>
                        Automatic reminder emails will be sent exactly on these days before expiration. Default: 60, 30, 7 days before contract ends.
                    </flux:description>
                    <flux:error name="reminder_days" />
                </flux:field>
            </div>
        </div>

        <!-- Ticket Settings -->
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-zinc-900">
            <h2 class="mb-4 text-lg font-semibold text-neutral-900 dark:text-white">Ticket System</h2>
            <div class="space-y-4">
                <flux:field>
                    <flux:label>Ticket Cutoff Time</flux:label>
                    <flux:input type="time" wire:model="ticket_cutoff_time" required />
                    <flux:description>Tickets created after this time will have the next day's date. Default: 17:00 (5 PM)</flux:description>
                    <flux:error name="ticket_cutoff_time" />
                </flux:field>
            </div>
        </div>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">Save Settings</flux:button>
        </div>
    </form>
</div>
