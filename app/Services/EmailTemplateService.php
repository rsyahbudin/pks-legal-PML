<?php

namespace App\Services;

use App\Models\Setting;

class EmailTemplateService
{
    /**
     * Get legal team email from settings.
     */
    public function getLegalTeamEmail(): string
    {
        return Setting::get('legal_team_email', 'legal@pfimegalife.co.id');
    }

    /**
     * Get ticket created email template.
     */
    public function getTicketCreatedTemplate(): array
    {
        return [
            'subject' => Setting::get('ticket_created_email_subject', 'Ticket Baru: {ticket_number}'),
            'body' => Setting::get('ticket_created_email_body', $this->getDefaultTicketCreatedBody()),
        ];
    }

    /**
     * Get ticket status changed email template.
     */
    public function getTicketStatusChangedTemplate(): array
    {
        return [
            'subject' => Setting::get('ticket_status_changed_email_subject', 'Status Ticket {ticket_number} Berubah'),
            'body' => Setting::get('ticket_status_changed_email_body', $this->getDefaultTicketStatusChangedBody()),
        ];
    }

    /**
     * Get contract status changed email template.
     */
    public function getContractStatusChangedTemplate(): array
    {
        return [
            'subject' => Setting::get('contract_status_changed_email_subject', 'Status Kontrak {contract_number} Berubah'),
            'body' => Setting::get('contract_status_changed_email_body', $this->getDefaultContractStatusChangedBody()),
        ];
    }

    /**
     * Parse placeholders in template.
     */
    public function parsePlaceholders(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            $template = str_replace('{'.$key.'}', $value ?? '', $template);
        }

        // Remove any remaining placeholders that weren't replaced
        $template = preg_replace('/\{[^}]+\}/', '', $template);

        return $template;
    }

    /**
     * Default ticket created email body.
     */
    private function getDefaultTicketCreatedBody(): string
    {
        return "Halo Tim Legal,\n\nTicket baru telah dibuat:\n\nNomor Ticket: {ticket_number}\nJudul Dokumen: {proposed_document_title}\nJenis Dokumen: {document_type}\nDibuat oleh: {creator_name}\nDivisi: {division_name}\nDepartemen: {department_name}\nTanggal: {created_at}\n\nSilakan review ticket ini di dashboard Anda.\n\nTerima kasih,\nSistem Ticketing Legal";
    }

    /**
     * Default ticket status changed email body.
     */
    private function getDefaultTicketStatusChangedBody(): string
    {
        return "Halo,\n\nStatus ticket telah berubah:\n\nNomor Ticket: {ticket_number}\nJudul Dokumen: {proposed_document_title}\nStatus Sebelumnya: {old_status}\nStatus Baru: {new_status}\nDiubah oleh: {reviewed_by}\nTanggal: {reviewed_at}\n{rejection_reason}\n\nSilakan cek detail ticket di dashboard Anda.\n\nTerima kasih,\nSistem Ticketing Legal";
    }

    /**
     * Default contract status changed email body.
     */
    private function getDefaultContractStatusChangedBody(): string
    {
        return "Halo,\n\nStatus kontrak telah berubah:\n\nNomor Kontrak: {contract_number}\nNama Perjanjian: {agreement_name}\nStatus Sebelumnya: {old_status}\nStatus Baru: {new_status}\nTanggal Mulai: {start_date}\nTanggal Berakhir: {end_date}\n{termination_reason}\n\nSilakan cek detail kontrak di dashboard Anda.\n\nTerima kasih,\nSistem Ticketing Legal";
    }
}
