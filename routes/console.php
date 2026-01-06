<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule contract reminders (only if settings table exists)
if (Schema::hasTable('settings')) {
    $sendTime = \App\Models\Setting::get('reminder_send_time', '08:00');
} else {
    $sendTime = '08:00';
}

Schedule::command('contracts:send-reminders')
    ->dailyAt($sendTime)
    ->description('Send contract expiry reminder emails');
