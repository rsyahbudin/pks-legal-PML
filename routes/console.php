<?php

use App\Console\Commands\SendContractReminders;
use App\Console\Commands\UpdateExpiredContracts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Update expired contracts daily at midnight
Schedule::command(UpdateExpiredContracts::class)->daily();

// Send contract reminders daily at configured time (default 08:00)
$reminderTime = \App\Models\Setting::get('reminder_schedule_time', '08:00');
Schedule::command(SendContractReminders::class)->dailyAt($reminderTime);
