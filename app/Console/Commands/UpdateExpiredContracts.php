<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class UpdateExpiredContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:update-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update contract status to expired when CONTR_END_DT has passed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired contracts...');

        // Find all active contracts that have passed their end date
        $expiredContracts = Contract::with('ticket')
            ->whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'active'))
            ->whereNotNull('CONTR_END_DT')
            ->whereDate('CONTR_END_DT', '<', now())
            ->get();

        if ($expiredContracts->isEmpty()) {
            $this->info('No expired contracts found.');

            return Command::SUCCESS;
        }

        $count = 0;
        $notificationService = app(NotificationService::class);

        foreach ($expiredContracts as $contract) {
            $oldStatus = $contract->status?->code ?? 'active';
            $contract->update(['CONTR_STS_ID' => \App\Models\ContractStatus::getIdByCode('expired')]);

            // Auto-close the related ticket
            if ($contract->ticket && $contract->ticket->TCKT_STS_ID !== \App\Models\TicketStatus::getIdByCode('closed')) {
                $contract->ticket->update(['TCKT_STS_ID' => \App\Models\TicketStatus::getIdByCode('closed')]);
                $this->line("  → Ticket #{$contract->ticket->TCKT_NO} auto-closed");
            }

            // Send notification about status change
            $notificationService->notifyContractStatusChanged($contract, $oldStatus, 'expired');

            $count++;
            $this->line("✓ Contract #{$contract->CONTR_NO} marked as expired");
        }

        $this->info("Successfully updated {$count} contract(s) to expired status.");

        return Command::SUCCESS;
    }
}
