<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add email template settings using the existing settings table structure
        $emailSettings = [
            [
                'key' => 'legal_team_email',
                'value' => 'legal@pfimegalife.co.id',
                'type' => 'string',
                'description' => 'Email tim legal untuk menerima notifikasi ticket baru',
            ],
            [
                'key' => 'ticket_created_email_subject',
                'value' => 'Ticket Baru: {ticket_number} - {proposed_document_title}',
                'type' => 'string',
                'description' => 'Subject email untuk ticket baru. Placeholders: {ticket_number}, {proposed_document_title}, {creator_name}, {division_name}',
            ],
            [
                'key' => 'ticket_created_email_body',
                'value' => "Halo Tim Legal,\n\nTicket baru telah dibuat:\n\nNomor Ticket: {ticket_number}\nJudul Dokumen: {proposed_document_title}\nJenis Dokumen: {document_type}\nDibuat oleh: {creator_name}\nDivisi: {division_name}\nDepartemen: {department_name}\nTanggal: {created_at}\n\nSilakan review ticket ini di dashboard Anda.\n\nTerima kasih,\nSistem Ticketing Legal",
                'type' => 'text',
                'description' => 'Body email untuk ticket baru. Placeholders: {ticket_number}, {proposed_document_title}, {document_type}, {creator_name}, {division_name}, {department_name}, {created_at}',
            ],
            [
                'key' => 'ticket_status_changed_email_subject',
                'value' => 'Status Ticket {ticket_number} Berubah: {new_status}',
                'type' => 'string',
                'description' => 'Subject email untuk perubahan status ticket. Placeholders: {ticket_number}, {old_status}, {new_status}, {proposed_document_title}',
            ],
            [
                'key' => 'ticket_status_changed_email_body',
                'value' => "Halo,\n\nStatus ticket telah berubah:\n\nNomor Ticket: {ticket_number}\nJudul Dokumen: {proposed_document_title}\nStatus Sebelumnya: {old_status}\nStatus Baru: {new_status}\nDiubah oleh: {reviewed_by}\nTanggal: {reviewed_at}\n{rejection_reason}\n\nSilakan cek detail ticket di dashboard Anda.\n\nTerima kasih,\nSistem Ticketing Legal",
                'type' => 'text',
                'description' => 'Body email untuk perubahan status ticket. Placeholders: {ticket_number}, {proposed_document_title}, {old_status}, {new_status}, {reviewed_by}, {reviewed_at}, {rejection_reason}',
            ],
            [
                'key' => 'contract_status_changed_email_subject',
                'value' => 'Status Kontrak {contract_number} Berubah: {new_status}',
                'type' => 'string',
                'description' => 'Subject email untuk perubahan status kontrak. Placeholders: {contract_number}, {old_status}, {new_status}, {agreement_name}',
            ],
            [
                'key' => 'contract_status_changed_email_body',
                'value' => "Halo,\n\nStatus kontrak telah berubah:\n\nNomor Kontrak: {contract_number}\nNama Perjanjian: {agreement_name}\nStatus Sebelumnya: {old_status}\nStatus Baru: {new_status}\nTanggal Mulai: {start_date}\nTanggal Berakhir: {end_date}\n{termination_reason}\n\nSilakan cek detail kontrak di dashboard Anda.\n\nTerima kasih,\nSistem Ticketing Legal",
                'type' => 'text',
                'description' => 'Body email untuk perubahan status kontrak. Placeholders: {contract_number}, {agreement_name}, {old_status}, {new_status}, {start_date}, {end_date}, {termination_reason}',
            ],
        ];

        foreach ($emailSettings as $setting) {
            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        $keys = [
            'legal_team_email',
            'ticket_created_email_subject',
            'ticket_created_email_body',
            'ticket_status_changed_email_subject',
            'ticket_status_changed_email_body',
            'contract_status_changed_email_subject',
            'contract_status_changed_email_body',
        ];

        DB::table('settings')->whereIn('key', $keys)->delete();
    }
};
