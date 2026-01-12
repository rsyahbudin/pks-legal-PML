<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use App\Console\Commands\ExpireContracts;
use App\Console\Commands\SendContractReminders;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Expire contracts daily at midnight
Schedule::command(ExpireContracts::class)->daily();

// Send contract reminders daily at configured time (default 08:00)
$reminderTime = \App\Models\Setting::get('reminder_schedule_time', '08:00');
Schedule::command(SendContractReminders::class)->dailyAt($reminderTime);
