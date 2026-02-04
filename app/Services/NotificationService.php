<?php

namespace App\Services;

use App\Mail\DynamicMail;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function __construct(
        private EmailTemplateService $templateService
    ) {}

    /**
     * Notify when a new ticket is created.
     */
    public function notifyTicketCreated(Ticket $ticket): void
    {
        // Ensure relationships are loaded
        $ticket->load(['creator', 'division', 'department']);

        $template = $this->templateService->getTicketCreatedTemplate();

        $data = [
            'ticket_number' => $ticket->ticket_number,
            'proposed_document_title' => $ticket->proposed_document_title,
            'document_type' => $ticket->document_type_label,
            'creator_name' => $ticket->creator->name,
            'creator_email' => $ticket->creator->email,
            'division_name' => $ticket->division->name,
            'department_name' => $ticket->department?->name ?? '-',
            'created_at' => $ticket->created_at->format('d M Y H:i'),
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        // Get legal department email
        $legalEmail = Department::getLegalEmail();

        try {
            // Get department email and CC emails
            $departmentEmail = $ticket->department?->email;
            $deptCcEmails = [];

            if ($ticket->department && ! empty($ticket->department->cc_emails)) {
                $deptCcEmails = array_filter($ticket->department->cc_emails_list, function ($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }

            // TO: Legal Department
            $to = $legalEmail;

            // CC: Creator + Creator Department + Creator Dept CC Emails
            $cc = [$ticket->creator->email];

            if ($departmentEmail && filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $departmentEmail;
            }

            if (! empty($deptCcEmails)) {
                $cc = array_merge($cc, $deptCcEmails);
            }

            // Remove duplicates
            $cc = array_unique(array_filter($cc));

            \Log::info('Sending ticket created email', [
                'ticket_id' => $ticket->id,
                'to' => $to,
                'cc' => $cc,
            ]);

            // Send email
            if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $mailable = new DynamicMail($subject, $body, $data);

                if (! empty($cc)) {
                    Mail::to($to)->cc(array_values($cc))->send($mailable);
                } else {
                    Mail::to($to)->send($mailable);
                }

                \Log::info('Ticket created email sent successfully', ['ticket_id' => $ticket->id]);
            } else {
                \Log::warning('No valid legal email found', ['ticket_id' => $ticket->id]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send ticket created email', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - let ticket creation succeed even if email fails
        }

        // Create notification for legal users
        $this->createNotificationForLegalTeam(
            "New ticket #{$ticket->ticket_number} requires review",
            $ticket
        );
    }

    /**
     * Notify when ticket status changes.
     */
    public function notifyTicketStatusChanged(Ticket $ticket, string $oldStatus, string $newStatus): void
    {
        $template = $this->templateService->getTicketStatusChangedTemplate();

        $data = [
            'ticket_number' => $ticket->ticket_number,
            'proposed_document_title' => $ticket->proposed_document_title,
            'document_type' => $ticket->document_type_label,
            'old_status' => $this->getStatusLabel($oldStatus),
            'new_status' => $this->getStatusLabel($newStatus),
            'reviewed_by' => $ticket->reviewer?->name ?? 'System',
            'reviewed_at' => $ticket->reviewed_at?->format('d M Y H:i') ?? now()->format('d M Y H:i'),
            'rejection_reason' => $ticket->rejection_reason ? "\nAlasan Penolakan: {$ticket->rejection_reason}" : '',
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        try {
            // TO: Creator
            $to = $ticket->creator->email;

            // Prepare CC list
            $cc = [];

            // Get legal department email and CC emails
            $legalEmail = Department::getLegalEmail();
            $legalDept = Department::where('code', 'LEGAL')->orWhere('name', 'LIKE', '%Legal%')->first();

            if ($legalEmail && filter_var($legalEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $legalEmail;
            }

            // Add Legal Department CC Emails
            if ($legalDept && ! empty($legalDept->cc_emails)) {
                $legalCcEmails = array_filter($legalDept->cc_emails_list, function ($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
                $cc = array_merge($cc, $legalCcEmails);
            }

            // Get creator department email and CC emails
            $departmentEmail = $ticket->department?->email;

            if ($departmentEmail && filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
                $cc[] = $departmentEmail;
            }

            // Add Creator Department CC Emails
            if ($ticket->department && ! empty($ticket->department->cc_emails)) {
                $deptCcEmails = array_filter($ticket->department->cc_emails_list, function ($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
                $cc = array_merge($cc, $deptCcEmails);
            }

            // Remove duplicates and filter out the creator (TO recipient)
            $cc = array_unique(array_filter($cc, function ($email) use ($to) {
                return $email !== $to;
            }));

            // Send email
            $mailable = new DynamicMail($subject, $body, $data);

            if (! empty($cc)) {
                Mail::to($to)->cc(array_values($cc))->send($mailable);
            } else {
                Mail::to($to)->send($mailable);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to send ticket status change email', [
                'ticket_id' => $ticket->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Create notifications
        $this->createNotificationForUser(
            $ticket->creator,
            "Ticket #{$ticket->ticket_number} status changed: {$this->getStatusLabel($newStatus)}",
            $ticket
        );

        $this->createNotificationForLegalTeam(
            "Ticket #{$ticket->ticket_number} status changed: {$this->getStatusLabel($newStatus)}",
            $ticket
        );
    }

    /**
     * Notify when contract status changes.
     */
    public function notifyContractStatusChanged(Contract $contract, string $oldStatus, string $newStatus): void
    {
        $template = $this->templateService->getContractStatusChangedTemplate();

        $data = [
            'contract_number' => $contract->contract_number,
            'agreement_name' => $contract->agreement_name,
            'document_type' => $contract->document_type_label,
            'old_status' => $this->getStatusLabel($oldStatus),
            'new_status' => $this->getStatusLabel($newStatus),
            'start_date' => $contract->start_date?->format('d M Y') ?? '-',
            'end_date' => $contract->end_date?->format('d M Y') ?? '-',
            'termination_reason' => $contract->termination_reason ? "\nAlasan Terminasi: {$contract->termination_reason}" : '',
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        // Send notification
        if ($contract->creator) {
            try {
                // TO: Creator
                $to = $contract->creator->email;

                // Prepare CC list
                $cc = [];

                // Get legal department email and CC emails
                $legalEmail = Department::getLegalEmail();
                $legalDept = Department::where('code', 'LEGAL')->orWhere('name', 'LIKE', '%Legal%')->first();

                if ($legalEmail && filter_var($legalEmail, FILTER_VALIDATE_EMAIL)) {
                    $cc[] = $legalEmail;
                }

                // Add Legal Department CC Emails
                if ($legalDept && ! empty($legalDept->cc_emails)) {
                    $legalCcEmails = array_filter($legalDept->cc_emails_list, function ($email) {
                        return filter_var($email, FILTER_VALIDATE_EMAIL);
                    });
                    $cc = array_merge($cc, $legalCcEmails);
                }

                // Get contract department email and CC emails
                $departmentEmail = $contract->department?->email;

                if ($departmentEmail && filter_var($departmentEmail, FILTER_VALIDATE_EMAIL)) {
                    $cc[] = $departmentEmail;
                }

                // Add Contract Department CC Emails
                if ($contract->department && ! empty($contract->department->cc_emails)) {
                    $deptCcEmails = array_filter($contract->department->cc_emails_list, function ($email) {
                        return filter_var($email, FILTER_VALIDATE_EMAIL);
                    });
                    $cc = array_merge($cc, $deptCcEmails);
                }

                // Remove duplicates and filter out the creator (TO recipient)
                $cc = array_unique(array_filter($cc, function ($email) use ($to) {
                    return $email !== $to;
                }));

                // Send email
                $mailable = new DynamicMail($subject, $body, $data);

                if (! empty($cc)) {
                    Mail::to($to)->cc(array_values($cc))->send($mailable);
                } else {
                    Mail::to($to)->send($mailable);
                }
            } catch (\Exception $e) {
                \Log::error('Failed to send contract status change email', [
                    'contract_id' => $contract->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Create notifications
        if ($contract->creator) {
            $this->createNotificationForUser(
                $contract->creator,
                "Contract #{$contract->contract_number} status changed: {$newStatus}",
                $contract
            );
        }

        $this->createNotificationForLegalTeam(
            "Contract #{$contract->contract_number} status changed: {$newStatus}",
            $contract
        );
    }

    /**
     * Create notification for a specific user.
     */
    private function createNotificationForUser(User $user, string $message, $notifiable): void
    {
        $user->internalNotifications()->create([
            'type' => 'info',
            'title' => $message,
            'message' => $message,
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
        ]);
    }

    /**
     * Create notification for all legal team users.
     */
    private function createNotificationForLegalTeam(string $message, $notifiable): void
    {
        $legalUsers = User::getAdminAndLegalUsers();

        foreach ($legalUsers as $user) {
            $this->createNotificationForUser($user, $message, $notifiable);
        }
    }

    /**
     * Get human-readable status label from database.
     */
    private function getStatusLabel(string $status): string
    {
        // Try to get status name from database
        $ticketStatus = \App\Models\TicketStatus::where('code', $status)->first();

        if ($ticketStatus) {
            return $ticketStatus->name; // English name from database
        }

        // Fallback to ucfirst if not found
        return ucfirst(str_replace('_', ' ', $status));
    }
}
