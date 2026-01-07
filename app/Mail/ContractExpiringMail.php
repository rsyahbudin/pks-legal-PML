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

class ContractExpiringMail extends Mailable
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
        $subjectTemplate = Setting::get('email_reminder_subject', 'Agreement [XX] – Expiration Reminder');
        $bodyTemplate = Setting::get('email_reminder_body', '');
        
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
            '[XX]' => $this->contract->agreement_name ?? $this->contract->contract_number,
            '[agreement name]' => $this->contract->agreement_name ?? '',
            '[contract number]' => $this->contract->contract_number,
            '[expiration date]' => $expirationDate,
            '[partner name]' => $this->contract->partner->display_name ?? '',
            '[days remaining]' => $this->daysRemaining,
        ];
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
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
