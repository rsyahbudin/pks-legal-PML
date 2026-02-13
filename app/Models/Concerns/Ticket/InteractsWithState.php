<?php

namespace App\Models\Concerns\Ticket;

use App\Models\User;

/**
 * Provides the logActivity helper for ticket-related activity logging.
 *
 * State transitions and business logic have been extracted
 * to App\Services\TicketService and App\Services\ContractService.
 */
trait InteractsWithState
{
    /**
     * Log activity for this ticket via the polymorphic relationship.
     */
    public function logActivity(string $message, ?User $user = null): void
    {
        $this->activityLogs()->create([
            'LOG_CAUSER_ID' => $user?->LGL_ROW_ID ?? auth()->id(),
            'LOG_CAUSER_TYPE' => User::class,
            'LOG_EVENT' => 'custom',
            'LOG_DESC' => $message,
            'LOG_PROPERTIES' => [
                'ticket_number' => $this->TCKT_NO,
                'status' => $this->status?->LOV_VALUE,
            ],
            'LOG_NAME' => 'ticket_activity',
        ]);
    }
}
