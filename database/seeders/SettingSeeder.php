<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Application settings
            ['key' => 'app_name', 'value' => 'PKS Tracking System', 'type' => 'string'],
            ['key' => 'company_name', 'value' => 'PFI Mega Life', 'type' => 'string'],
            
            // Email reminder settings
            ['key' => 'reminder_email_enabled', 'value' => 'true', 'type' => 'boolean'],
            ['key' => 'legal_team_email', 'value' => 'muhammad.syahbudin@pfimegalife.co.id', 'type' => 'string'],
            ['key' => 'reminder_send_time', 'value' => '08:00', 'type' => 'string'],
            ['key' => 'reminder_days', 'value' => json_encode([60, 30, 7]), 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
