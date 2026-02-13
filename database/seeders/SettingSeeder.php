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
            ['CONFIG_KEY' => 'app_name', 'CONFIG_VALUE' => 'PKS Tracking System', 'type' => 'string'],
            ['CONFIG_KEY' => 'company_name', 'CONFIG_VALUE' => 'PFI Mega Life', 'type' => 'string'],

            // Email reminder settings
            ['CONFIG_KEY' => 'reminder_email_enabled', 'CONFIG_VALUE' => 'true', 'type' => 'boolean'],
            ['CONFIG_KEY' => 'legal_team_email', 'CONFIG_VALUE' => 'muhammad.syahbudin@pfimegalife.co.id', 'type' => 'string'],
            ['CONFIG_KEY' => 'reminder_send_time', 'CONFIG_VALUE' => '08:00', 'type' => 'string'],
            ['CONFIG_KEY' => 'reminder_days', 'CONFIG_VALUE' => json_encode([60, 30, 7]), 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['CONFIG_KEY' => $setting['CONFIG_KEY']],
                $setting
            );
        }
    }
}
