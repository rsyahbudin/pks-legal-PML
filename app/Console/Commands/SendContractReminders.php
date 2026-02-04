<?php

namespace App\Console\Commands;

use App\Mail\ContractExpiringMail;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Notification;
use App\Models\ReminderLog;
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
        if (! Setting::get('reminder_email_enabled', true)) {
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

        // Get Document Type IDs for included types
        $documentTypeIds = \App\Models\DocumentType::whereIn('code', $includedTypes)->pluck('id')->toArray();
        $activeStatusId = \App\Models\ContractStatus::getIdByCode('active');

        // Get all active contracts with these document types
        $maxDays = max($reminderDays);
        $contracts = Contract::with(['division', 'pic', 'ticket', 'ticket.creator'])
            ->where('status_id', $activeStatusId) // Only active contracts
            ->whereIn('document_type_id', $documentTypeIds)
            ->whereDate('end_date', '>=', now()) // Not expired
            ->whereDate('end_date', '<=', now()->addDays($maxDays)) // Within max reminder range
            ->get();

        // Filter to only contracts with exact match on reminder days
        $contracts = $contracts->filter(function ($contract) use ($reminderDays) {
            $daysRemaining = $contract->days_remaining;

            return in_array($daysRemaining, $reminderDays);
        });

        if ($contracts->isEmpty()) {
            $this->info('No contracts needing reminders.');

            return self::SUCCESS;
        }

        $this->info("Found {$contracts->count()} contracts to process.");

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
                    // TO: Creator/PIC
                    $to = $recipient->email;

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

                    // Remove duplicates and filter out the recipient
                    $cc = array_unique(array_filter($cc, function ($email) use ($to) {
                        return $email !== $to;
                    }));

                    // Send email with CC
                    $mail = Mail::to($to);
                    if (! empty($cc)) {
                        $mail->cc(array_values($cc));
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

                    $ccInfo = ! empty($ccEmails) ? ' (CC: '.implode(', ', $ccEmails).')' : '';
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

            // Always send to legal department email if configured
            $legalEmail = Department::getLegalEmail();
            if ($legalEmail && filter_var($legalEmail, FILTER_VALIDATE_EMAIL)) {
                try {
                    Mail::to($legalEmail)->send(new ContractExpiringMail($contract, $daysRemaining));
                    $this->info("Sent reminder to legal department email: {$legalEmail}");
                } catch (\Exception $e) {
                    $this->error("Failed to send to legal email: {$e->getMessage()}");
                }
            }
        }

        $this->info("Completed. Sent {$sentCount} email reminders.");

        return self::SUCCESS;
    }
}
