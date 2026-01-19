<?php

namespace App\Services;

use App\Mail\DynamicMail;
use App\Models\Contract;
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

        // Send to legal team with department CC emails
        $legalEmail = $this->templateService->getLegalTeamEmail();
        
        try {
            // Get department CC emails if available
            $ccEmails = [];
            if ($ticket->department && !empty($ticket->department->cc_emails)) {
                $ccEmails = array_filter($ticket->department->cc_emails_list, function($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }
            
            \Log::info('Sending ticket created emails', [
                'ticket_id' => $ticket->id,
                'creator_email' => $ticket->creator->email,
                'legal_email' => $legalEmail,
                'cc_emails' => $ccEmails,
            ]);
            
            // Send email - use array_values to reindex the filtered array
            $mailable = new DynamicMail($subject, $body, $data);
            
            // Send to creator with CC
            if (!empty($ccEmails)) {
                Mail::to($ticket->creator->email)->cc(array_values($ccEmails))->send($mailable);
            } else {
                Mail::to($ticket->creator->email)->send($mailable);
            }
            
            // Send to legal team with CC
            if (!empty($ccEmails)) {
                Mail::to($legalEmail)->cc(array_values($ccEmails))->send(new DynamicMail($subject, $body, $data));
            } else {
                Mail::to($legalEmail)->send(new DynamicMail($subject, $body, $data));
            }
            
            \Log::info('Ticket created emails sent successfully', ['ticket_id' => $ticket->id]);
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
            "Ticket baru #{$ticket->ticket_number} memerlukan review",
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
            'old_status' => $this->getStatusLabel($oldStatus),
            'new_status' => $this->getStatusLabel($newStatus),
            'reviewed_by' => $ticket->reviewer?->name ?? 'System',
            'reviewed_at' => $ticket->reviewed_at?->format('d M Y H:i') ?? now()->format('d M Y H:i'),
            'rejection_reason' => $ticket->rejection_reason ? "\nAlasan Penolakan: {$ticket->rejection_reason}" : '',
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        try {
            // Get department CC emails if available
            $ccEmails = [];
            if ($ticket->department && !empty($ticket->department->cc_emails)) {
                $ccEmails = array_filter($ticket->department->cc_emails_list, function($email) {
                    return filter_var($email, FILTER_VALIDATE_EMAIL);
                });
            }
            
            $mailable = new DynamicMail($subject, $body, $data);
            
            // Send to creator
            if (!empty($ccEmails)) {
                Mail::to($ticket->creator->email)->cc(array_values($ccEmails))->send($mailable);
            } else {
                Mail::to($ticket->creator->email)->send($mailable);
            }

            // Send to legal team
            $legalEmail = $this->templateService->getLegalTeamEmail();
            if (!empty($ccEmails)) {
                Mail::to($legalEmail)->cc(array_values($ccEmails))->send(new DynamicMail($subject, $body, $data));
            } else {
                Mail::to($legalEmail)->send(new DynamicMail($subject, $body, $data));
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
            "Ticket #{$ticket->ticket_number} status berubah: {$this->getStatusLabel($newStatus)}",
            $ticket
        );

        $this->createNotificationForLegalTeam(
            "Ticket #{$ticket->ticket_number} status berubah: {$this->getStatusLabel($newStatus)}",
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
            'old_status' => ucfirst($oldStatus),
            'new_status' => ucfirst($newStatus),
            'start_date' => $contract->start_date?->format('d M Y') ?? '-',
            'end_date' => $contract->end_date?->format('d M Y') ?? '-',
            'termination_reason' => $contract->termination_reason ? "\nAlasan Terminasi: {$contract->termination_reason}" : '',
        ];

        $subject = $this->templateService->parsePlaceholders($template['subject'], $data);
        $body = $this->templateService->parsePlaceholders($template['body'], $data);

        // Send to creator
        if ($contract->creator) {
            Mail::to($contract->creator->email)->send(new DynamicMail($subject, $body, $data));
        }

        // Send to legal team
        $legalEmail = $this->templateService->getLegalTeamEmail();
        Mail::to($legalEmail)->send(new DynamicMail($subject, $body, $data));

        // Create notifications
        if ($contract->creator) {
            $this->createNotificationForUser(
                $contract->creator,
                "Kontrak #{$contract->contract_number} status berubah: {$newStatus}",
                $contract
            );
        }

        $this->createNotificationForLegalTeam(
            "Kontrak #{$contract->contract_number} status berubah: {$newStatus}",
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
     * Get human-readable status label.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Open',
            'on_process' => 'On Process',
            'done' => 'Done',
            'rejected' => 'Rejected',
            'closed' => 'Closed',
            'active' => 'Active',
            'expired' => 'Expired',
            'terminated' => 'Terminated',
            default => ucfirst($status),
        };
    }
}
