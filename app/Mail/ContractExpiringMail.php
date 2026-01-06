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

    public function __construct(
        public Contract $contract,
        public int $daysRemaining,
        ?string $replyToEmail = null,
        ?string $replyToName = null
    ) {
        $this->replyToEmail = $replyToEmail;
        $this->replyToName = $replyToName;
    }

    public function envelope(): Envelope
    {
        $subject = $this->daysRemaining <= 0
            ? "[URGENT] Kontrak {$this->contract->contract_number} Sudah Expired"
            : "[Reminder] Kontrak {$this->contract->contract_number} Akan Expired dalam {$this->daysRemaining} Hari";

        $replyTo = [];
        if ($this->replyToEmail) {
            $replyTo[] = new Address($this->replyToEmail, $this->replyToName ?? '');
        }

        return new Envelope(
            subject: $subject,
            replyTo: $replyTo
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contract-expiring');
    }
}
