<?php

namespace App\Mail;

use App\Models\Contract;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

use Illuminate\Contracts\Queue\ShouldQueue;

class ContractExpiringMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ?string $replyToEmail = null;
    public ?string $replyToName = null;
    public string $emailSubject;
    public string $emailBody;

    public function __construct(
        public Contract $contract,
        public int $daysRemaining,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ) {
        $this->replyToEmail = $replyToEmail;
        $this->replyToName = $replyToName;
        
        // Load templates from settings
        $subjectTemplate = Setting::get('contract_reminder_email_subject', 'Agreement {agreement_name} – Expiration Reminder');
        $bodyTemplate = Setting::get('contract_reminder_email_body', $this->getDefaultBody());
        
        // Replace placeholders
        $this->emailSubject = $this->replacePlaceholders($subjectTemplate);
        $this->emailBody = $this->replacePlaceholders($bodyTemplate);
    }
    
    protected function replacePlaceholders(string $template): string
    {
        $expirationDate = $this->contract->end_date 
            ? $this->contract->end_date->format('d F Y')
            : 'Auto Renewal';
            
        $replacements = [
            '{agreement_name}' => $this->contract->agreement_name ?? '',
            '{contract_number}' => $this->contract->contract_number,
            '{end_date}' => $expirationDate,
            '{days_remaining}' => $this->daysRemaining,
            '{counterpart_name}' => $this->contract->description ?? '',
        ];
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }
    
    protected function getDefaultBody(): string
    {
        return "Dear Sir/Madam,\n\nWe would like to inform you that Agreement {agreement_name} will expire on {end_date}.\n\nIn this regard, we kindly request your confirmation regarding the extension of the said agreement. Should you wish to proceed with the renewal, please contact us at legal@pfimegalife.co.id. Otherwise, kindly disregard this reminder.\n\nThank you for your attention and cooperation.\n\nBest regards,\nLegal & Corporate Secretary";
    }

    public function envelope(): Envelope
    {
        $replyTo = [];
        if ($this->replyToEmail) {
            $replyTo[] = new Address($this->replyToEmail, $this->replyToName ?? '');
        }

        return new Envelope(
            subject: $this->emailSubject,
            replyTo: $replyTo
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contract-expiring');
    }
}
