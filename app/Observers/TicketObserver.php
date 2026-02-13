<?php

namespace App\Observers;

use App\Models\Setting;
use App\Models\Ticket;

class TicketObserver
{
    /**
     * Handle the Ticket "creating" event.
     */
    public function creating(Ticket $ticket): void
    {
        // Auto-generate ticket number
        if (! $ticket->TCKT_NO) {
            // Must ensure DIV_ID is set
            if ($ticket->DIV_ID) {
                $ticket->TCKT_NO = Ticket::generateTicketNumber($ticket->DIV_ID);
            }
        }

        // Adjust created_at if ticket created after cutoff time
        $now = now();
        $cutoffTime = Setting::get('ticket_cutoff_time', '17:00');
        $cutoffHour = (int) substr($cutoffTime, 0, 2); // Extract hour from HH:mm

        if ($now->hour >= $cutoffHour) {
            // Add 1 day to the date, keep the same time
            $ticket->TCKT_CREATED_DT = $now->addDay();
        }
    }
}
