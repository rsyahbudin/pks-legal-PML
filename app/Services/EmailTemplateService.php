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
            'subject' => Setting::get('ticket_created_email_subject', 'New Ticket: {ticket_number}'),
            'body' => Setting::get('ticket_created_email_body', $this->getDefaultTicketCreatedBody()),
        ];
    }

    /**
     * Get ticket status changed email template.
     */
    public function getTicketStatusChangedTemplate(): array
    {
        return [
            'subject' => Setting::get('ticket_status_changed_email_subject', 'Ticket {ticket_number} Status Changed'),
            'body' => Setting::get('ticket_status_changed_email_body', $this->getDefaultTicketStatusChangedBody()),
        ];
    }

    /**
     * Get contract status changed email template.
     */
    public function getContractStatusChangedTemplate(): array
    {
        return [
            'subject' => Setting::get('contract_status_changed_email_subject', 'Contract {contract_number} Status Changed'),
            'body' => Setting::get('contract_status_changed_email_body', $this->getDefaultContractStatusChangedBody()),
        ];
    }

    /**
     * Get contract reminder email template.
     */
    public function getContractReminderTemplate(): array
    {
        return [
            'subject' => Setting::get('contract_reminder_email_subject', 'Agreement {agreement_name} – Expiration Reminder'),
            'body' => Setting::get('contract_reminder_email_body', $this->getDefaultContractReminderBody()),
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
        return "Dear Sir/Madam,\n\nWe would like to inform you that a new ticket has been created with the following details:\n\nTicket Number: {ticket_number}\nDocument Title: {proposed_document_title}\nDocument Type: {document_type}\nCreated by: {creator_name}\nDivision: {division_name}\nDepartment: {department_name}\nDate: {created_at}\n\nWe kindly request your review of this ticket in your dashboard at your earliest convenience.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary";
    }

    /**
     * Default ticket status changed email body.
     */
    private function getDefaultTicketStatusChangedBody(): string
    {
        return "Dear Sir/Madam,\n\nWe would like to inform you that the status of ticket {ticket_number} has been updated:\n\nTicket Number: {ticket_number}\nDocument Title: {proposed_document_title}\nDocument Type: {document_type}\nPrevious Status: {old_status}\nNew Status: {new_status}\nChanged by: {reviewed_by}\nDate: {reviewed_at}\n{rejection_reason}\n\nPlease review the updated ticket details in your dashboard.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary";
    }

    /**
     * Default contract status changed email body.
     */
    private function getDefaultContractStatusChangedBody(): string
    {
        return "Dear Sir/Madam,\n\nWe would like to inform you that the status of contract {contract_number} has been updated:\n\nContract Number: {contract_number}\nAgreement Name: {agreement_name}\nDocument Type: {document_type}\nPrevious Status: {old_status}\nNew Status: {new_status}\nStart Date: {start_date}\nEnd Date: {end_date}\n{termination_reason}\n\nPlease review the updated contract details in your dashboard.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary";
    }

    /**
     * Default contract reminder email body.
     */
    private function getDefaultContractReminderBody(): string
    {
        return "Dear Sir/Madam,\n\nWe would like to inform you that Agreement {agreement_name} will expire on {end_date}.\n\nIn this regard, we kindly request your confirmation regarding the extension of the said agreement. Should you wish to proceed with the renewal, please contact us at legal@pfimegalife.co.id. Otherwise, kindly disregard this reminder.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary";
    }
}
