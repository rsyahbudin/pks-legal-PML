<?php

namespace App\Console\Commands;

use App\Mail\ContractExpiringMail;
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

        $warningThreshold = (int) Setting::get('reminder_threshold_warning', 60);
        $criticalThreshold = (int) Setting::get('reminder_threshold_critical', 30);

        // Get excluded document types from settings
        $excludedTypes = Setting::get('reminder_excluded_document_types', ['nda']);
        if (is_string($excludedTypes)) {
            $excludedTypes = json_decode($excludedTypes, true) ?? ['nda'];
        }

        // Get contracts that are expiring within warning threshold
        // Exclude: configured document types, terminated contracts
        $contracts = Contract::with(['partner', 'division', 'pic'])
            ->where('status', 'active') // Only active contracts
            ->whereNotIn('document_type', $excludedTypes)
            ->whereDate('end_date', '<=', now()->addDays($warningThreshold))
            ->whereDate('end_date', '>=', now()->subDays(7)) // Include recently expired (up to 7 days)
            ->get();

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
            $daysRemaining = $contract->days_remaining;
            $recipients = collect();

            // Add PIC (User or Manual)
            $picEmail = $contract->pic_email;
            if ($sendToPic && $picEmail) {
                // For manual PIC, we create a temporary object or struct
                // For User PIC, we already have the object, but we need consistency
                if ($contract->pic) {
                    $recipients->push($contract->pic);
                } else {
                    // Manual PIC - we'll handle this in the loop below or create a dummy object
                    // Let's create a simple object for manual PIC
                    $manualPic = new \stdClass();
                    $manualPic->id = 'manual_' . $contract->id; // Unique ID for deduplication
                    $manualPic->email = $picEmail;
                    $manualPic->name = $contract->pic_name;
                    $recipients->push($manualPic);
                }
            }

            // Add Legal team users
            if ($sendToLegal) {
                $legalRole = Role::where('slug', 'legal')->first();
                if ($legalRole) {
                    $legalUsers = User::where('role_id', $legalRole->id)->get();
                    $recipients = $recipients->merge($legalUsers);
                }
                // Also send to configured legal email
                if ($legalEmail && filter_var($legalEmail, FILTER_VALIDATE_EMAIL)) {
                    // We'll handle this separately
                }
            }

            // Add Management
            if ($sendToManagers) {
                $managementRole = Role::where('slug', 'management')->first();
                if ($managementRole) {
                    $managers = User::where('role_id', $managementRole->id)->get();
                    $recipients = $recipients->merge($managers);
                }
            }

            // Deduplicate recipients
            $recipients = $recipients->unique('id');

            foreach ($recipients as $recipient) {
                // Check if we already sent a reminder today (only for registered users)
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
                    } else {
                         // Log for manual PIC (we don't have a user ID so we might need to adjust logReminder or just skip)
                         // For now, let's skip logging to DB for manual PIC or log with null user_id if supported
                         // Assuming ReminderLog requires a user_id, we might skip or use a system user
                    }

                    $sentCount++;

                    $ccInfo = !empty($ccEmails) ? ' (CC: ' . implode(', ', $ccEmails) . ')' : '';
                    $this->info("Sent reminder to {$recipient->email}{$ccInfo} for contract {$contract->contract_number}");
                } catch (\Exception $e) {
                    $this->error("Failed to send to {$recipient->email}: {$e->getMessage()}");
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
