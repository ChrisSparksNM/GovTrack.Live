<?php

namespace App\Console\Commands;

use App\Services\CongressApiService;
use Illuminate\Console\Command;

class SyncBillsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bills:sync {--limit=100 : Number of bills to sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync bill data from Congress.gov API';

    /**
     * Execute the console command.
     */
    public function handle(CongressApiService $congressApi): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Starting bill synchronization (limit: {$limit})...");
        
        try {
            $syncedCount = $congressApi->syncBillData($limit);
            
            $this->info("Successfully synced {$syncedCount} bills.");
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error syncing bills: " . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
