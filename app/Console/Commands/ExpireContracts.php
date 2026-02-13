<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\ActivityLogService;
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
    public function handle(ActivityLogService $activityLogService): int
    {
        $this->info('Checking for contracts to expire...');

        $expiredContracts = Contract::whereHas('status', fn ($q) => $q->where('LOV_VALUE', 'active'))
            ->where('CONTR_IS_AUTO_RENEW', false)
            ->whereNotNull('CONTR_END_DT')
            ->whereDate('CONTR_END_DT', '<', now())
            ->get();

        if ($expiredContracts->isEmpty()) {
            $this->info('No contracts to expire.');

            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expiredContracts as $contract) {
            $oldValues = $contract->toArray();

            $contract->update(['CONTR_STS_ID' => \App\Models\ContractStatus::getIdByCode('expired')]);

            $activityLogService->logUpdated($contract, $oldValues);

            $count++;
            $this->line("Expired: {$contract->CONTR_NO}");
        }

        $this->info("Successfully expired {$count} contract(s).");

        return self::SUCCESS;
    }
}
