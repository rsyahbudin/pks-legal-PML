<?php

namespace App\Console\Commands;

use App\Mail\ContractExpiringMail;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\ReminderLog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendContractReminders extends Command
{
    protected $signature = 'contracts:send-reminders';

    protected $description = 'Send email reminders for contracts expiring soon';

    public function handle(): int
    {
        if (!Setting::get('reminder_email_enabled', true)) {
            $this->info('Email reminders are disabled.');
            return self::SUCCESS;
        }

        // Get reminder days configuration (default: 60, 30, 7 days before expiry)
        $reminderDays = Setting::get('reminder_days', [60, 30, 7]);
        if (is_string($reminderDays)) {
            $reminderDays = json_decode($reminderDays, true) ?? [60, 30, 7];
        }

        // Only process these document types
        $includedTypes = ['perjanjian', 'adendum', 'amandemen'];

        // Get all active contracts with these document types
        $maxDays = max($reminderDays);
        $contracts = Contract::with(['division', 'pic', 'ticket', 'ticket.creator'])
            ->where('status', 'active') // Only active contracts
            ->whereIn('document_type', $includedTypes)
            ->whereDate('end_date', '>=', now()) // Not expired
            ->whereDate('end_date', '<=', now()->addDays($maxDays)) // Within max reminder range
            ->get();

        // Filter to only contracts with exact match on reminder days
        $contracts = $contracts->filter(function($contract) use ($reminderDays) {
            $daysRemaining = $contract->days_remaining;
            return in_array($daysRemaining, $reminderDays);
        });

        if ($contracts->isEmpty()) {
            $this->info('No contracts needing reminders.');
            return self::SUCCESS;
        }

        $this->info("Found {$contracts->count()} contracts to process.");

        $sendToPic = Setting::get('reminder_email_pic', true);
        $sendToLegal = Setting::get('reminder_email_legal', true);
        $sendToManagers = Setting::get('reminder_email_managers', false);
        $legalEmail = Setting::get('legal_team_email', '');

        $sentCount = 0;

        foreach ($contracts as $contract) {
            $recipients = collect();

            // ALWAYS add ticket creator (if exists)
            if ($contract->ticket && $contract->ticket->creator) {
                $recipients->push($contract->ticket->creator);
            }

            // No deduplication here - ReminderLog::wasSentToday() will handle this per-contract
            $daysRemaining = $contract->days_remaining;

            foreach ($recipients as $recipient) {
                // Check if we already sent a reminder today for THIS contract to THIS user
                if ($recipient instanceof User && ReminderLog::wasSentToday($contract->id, $recipient->id, 'email')) {
                    continue;
                }

                try {
                    // Get CC emails from sub-division or division
                    $ccEmails = [];
                    if ($contract->subDivision && $contract->subDivision->cc_emails) {
                        $ccEmails = array_filter(array_map('trim', explode(',', $contract->subDivision->cc_emails)));
                    } elseif ($contract->division && $contract->division->cc_emails) {
                        $ccEmails = array_filter(array_map('trim', explode(',', $contract->division->cc_emails)));
                    }

                    // Send email with CC to division emails
                    $mail = Mail::to($recipient->email);
                    if (!empty($ccEmails)) {
                        $mail->cc($ccEmails);
                    }
                    $mail->send(new ContractExpiringMail($contract, $daysRemaining));

                    // Log the reminder
                    if ($recipient instanceof User) {
                        ReminderLog::logReminder($contract, $recipient, 'email', $daysRemaining);
                        // Create internal notification only for registered users
                        Notification::createContractReminder($recipient, $contract, $daysRemaining);
                    }

                    // Notify super admin and legal users
                    $adminUsers = User::getAdminAndLegalUsers();
                    foreach ($adminUsers as $admin) {
                        Notification::create([
                            'user_id' => $admin->id,
                            'title' => 'Reminder Email Sent',
                            'message' => "Auto reminder sent for contract {$contract->contract_number} to {$recipient->email}",
                            'type' => 'info',
                            'data' => [
                                'contract_id' => $contract->id,
                                'recipient_email' => $recipient->email,
                            ],
                        ]);
                    }

                    // Log activity
                    ActivityLog::create([
                        'loggable_type' => Contract::class,
                        'loggable_id' => $contract->id,
                        'user_id' => null, // System action
                        'action' => "Email reminder dikirim ke {$recipient->email}",
                        'description' => "Email pengingat otomatis berhasil dikirim ({$daysRemaining} hari lagi sebelum berakhir)",
                    ]);

                    $sentCount++;

                    $ccInfo = !empty($ccEmails) ? ' (CC: ' . implode(', ', $ccEmails) . ')' : '';
                    $this->info("Sent reminder to {$recipient->email}{$ccInfo} for contract {$contract->contract_number}");
                } catch (\Exception $e) {
                    $this->error("Failed to send to {$recipient->email}: {$e->getMessage()}");

                    // Notify admins/legal about failure
                    $adminUsers = User::getAdminAndLegalUsers();
                    foreach ($adminUsers as $admin) {
                        Notification::create([
                            'user_id' => $admin->id,
                            'title' => 'Reminder Email Failed',
                            'message' => "Failed to send auto reminder for contract {$contract->contract_number} to {$recipient->email}",
                            'type' => 'critical',
                            'data' => [
                                'contract_id' => $contract->id,
                                'recipient_email' => $recipient->email,
                                'error' => $e->getMessage(),
                            ],
                        ]);
                    }

                    // Log failure activity
                    ActivityLog::create([
                        'loggable_type' => Contract::class,
                        'loggable_id' => $contract->id,
                        'user_id' => null,
                        'action' => "Gagal mengirim email reminder ke {$recipient->email}",
                        'description' => "Email pengingat gagal dikirim: {$e->getMessage()}",
                    ]);
                }
            }

            // Send to legal team email if configured
            if ($sendToLegal && $legalEmail && filter_var($legalEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($legalEmail)->send(new ContractExpiringMail($contract, $daysRemaining));
                    $this->info("Sent reminder to legal team email: {$legalEmail}");
                } catch (\Exception $e) {
                    $this->error("Failed to send to legal email: {$e->getMessage()}");
                }
            }
        }

        $this->info("Completed. Sent {$sentCount} email reminders.");

        return self::SUCCESS;
    }
}
