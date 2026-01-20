<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Contract;
use Illuminate\Console\Command;

class ExpireContracts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:expire';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically expire contracts that have passed their end date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for contracts to expire...');

        // Find active contracts past their end date (excluding auto-renewal)
        $expiredContracts = Contract::whereHas('status', fn($q) => $q->where('code', 'active'))
            ->where('is_auto_renewal', false)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now())
            ->get();

        if ($expiredContracts->isEmpty()) {
            $this->info('No contracts to expire.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expiredContracts as $contract) {
            $oldValues = $contract->toArray();
            
            // Update status to expired
            $contract->update(['status_id' => \App\Models\ContractStatus::getIdByCode('expired')]);
            
            // Log the activity
            ActivityLog::logUpdated($contract, $oldValues, null); // null = system user
            
            $count++;
            $this->line("Expired: {$contract->contract_number}");
        }

        $this->info("Successfully expired {$count} contract(s).");
        return self::SUCCESS;
    }
}
