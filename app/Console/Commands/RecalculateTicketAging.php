<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

class RecalculateTicketAging extends Command
{
    protected $signature = 'tickets:recalculate-aging';

    protected $description = 'Recalculate aging_duration for completed tickets that are missing it';

    public function handle(): int
    {
        $this->info('Recalculating aging for completed tickets...');

        // Get all completed tickets (done, closed, rejected) without proper aging_duration
        $tickets = Ticket::whereHas('status', fn($q) => $q->whereIn('code', ['done', 'closed', 'rejected']))
            ->where(function ($q) {
                $q->whereNull('aging_duration')
                  ->orWhere('aging_duration', 0);
            })
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('No tickets need aging recalculation.');
            return self::SUCCESS;
        }

        $this->info("Found {$tickets->count()} tickets to recalculate.");
        $bar = $this->output->createProgressBar($tickets->count());

        $updated = 0;
        foreach ($tickets as $ticket) {
            $agingDuration = null;

            // Try to use aging_start_at and aging_end_at if available
            if ($ticket->aging_start_at && $ticket->aging_end_at) {
                $agingDuration = $ticket->aging_start_at->diffInMinutes($ticket->aging_end_at);
            }
            // Fall back to created_at to updated_at
            elseif ($ticket->created_at && $ticket->updated_at) {
                $agingDuration = $ticket->created_at->diffInMinutes($ticket->updated_at);
            }

            if ($agingDuration !== null && $agingDuration > 0) {
                $ticket->update(['aging_duration' => $agingDuration]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully recalculated aging for {$updated} tickets.");

        return self::SUCCESS;
    }
}
