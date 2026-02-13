<?php

namespace App\Observers;

use App\Models\Setting;
use App\Models\Ticket;
use App\Services\TicketService;

class TicketObserver
{
    public function __construct(
        private TicketService $ticketService
    ) {}

    /**
     * Handle the Ticket "creating" event.
     */
    public function creating(Ticket $ticket): void
    {
        // Auto-generate ticket number
        if (! $ticket->TCKT_NO && $ticket->DIV_ID) {
            $ticket->TCKT_NO = $this->ticketService->generateTicketNumber($ticket->DIV_ID);
        }

        // Adjust created_at if ticket created after cutoff time
        $now = now();
        $cutoffTime = Setting::get('ticket_cutoff_time', '17:00');
        $cutoffHour = (int) substr($cutoffTime, 0, 2);

        if ($now->hour >= $cutoffHour) {
            $ticket->TCKT_CREATED_DT = $now->addDay();
        }
    }
}
