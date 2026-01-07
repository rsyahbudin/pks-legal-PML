<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            [
                'key' => 'reminder_threshold_warning',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Threshold hari untuk status kuning (warning)',
            ],
            [
                'key' => 'reminder_threshold_critical',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Threshold hari untuk status merah (critical)',
            ],
            [
                'key' => 'reminder_email_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Aktifkan pengiriman email reminder otomatis',
            ],
            [
                'key' => 'reminder_email_pic',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Kirim email reminder ke PIC kontrak',
            ],
            [
                'key' => 'reminder_email_legal',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Kirim email reminder ke tim Legal',
            ],
            [
                'key' => 'reminder_email_managers',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Kirim email reminder ke Management',
            ],
            [
                'key' => 'legal_team_email',
                'value' => 'legal@company.com',
                'type' => 'string',
                'description' => 'Email tim Legal untuk menerima notifikasi',
            ],
            [
                'key' => 'reminder_send_time',
                'value' => '08:00',
                'type' => 'string',
                'description' => 'Waktu pengiriman reminder harian (format HH:mm)',
            ],
            [
                'key' => 'app_name',
                'value' => 'PKS Tracking System',
                'type' => 'string',
                'description' => 'Nama aplikasi',
            ],
            [
                'key' => 'company_name',
                'value' => 'PFI Mega Life',
                'type' => 'string',
                'description' => 'Nama perusahaan',
            ],
            [
                'key' => 'email_reminder_subject',
                'value' => 'Agreement [XX] – Expiration Reminder',
                'type' => 'text',
                'description' => 'Subject email reminder kontrak',
            ],
            [
                'key' => 'email_reminder_body',
                'value' => "Dear Sir/Madam,\n\nWe would like to inform you that Agreement [XX] will expire on [expiration date].\n\nIn this regard, we kindly request your confirmation regarding the extension of the said agreement. Should you wish to proceed with the renewal, please contact us at legal@pfimegalife.co.id. Otherwise, kindly disregard this reminder.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary",
                'type' => 'text',
                'description' => 'Body email reminder kontrak',
            ],
            [
                'key' => 'reminder_excluded_document_types',
                'value' => json_encode(['nda']),
                'type' => 'json',
                'description' => 'Jenis dokumen yang dikecualikan dari reminder otomatis',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
