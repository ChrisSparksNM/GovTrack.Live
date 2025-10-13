<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ManageCongressData extends Command
{
    protected $signature = 'congress:manage {action : Action to perform (status|update|full-scrape|schedule-status)}';
    protected $description = 'Manage Congress data scraping and updates';

    public function handle()
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'status':
                $this->showStatus();
                break;
            case 'update':
                $this->runUpdate();
                break;
            case 'full-scrape':
                $this->runFullScrape();
                break;
            case 'schedule-status':
                $this->showScheduleStatus();
                break;
            default:
                $this->error("Unknown action: {$action}");
                $this->showHelp();
                return 1;
        }

        return 0;
    }

    private function showStatus(): void
    {
        $this->info("Congress Data Management Status");
        $this->info(str_repeat('=', 50));
        
        // Show database stats
        $this->call('db:stats', ['--congress' => 119]);
        
        // Show recent updates
        $this->info("\nRecent Activity (Last 24 hours):");
        $this->call('show:recent-updates', [
            '--congress' => 119,
            '--days' => 1,
            '--limit' => 3
        ]);
    }

    private function runUpdate(): void
    {
        $this->info("Running daily Congress updates...");
        
        if ($this->confirm('Run update check for the last 2 days?', true)) {
            $this->call('scrape:congress-updates', [
                '--congress' => 119,
                '--days' => 2,
                '--batch-size' => 50
            ]);
        }
    }

    private function runFullScrape(): void
    {
        $this->warn("This will start a full scrape of all Congress 119 bills.");
        $this->warn("This process can take 24-72 hours to complete.");
        
        if (!$this->confirm('Are you sure you want to continue?', false)) {
            $this->info('Full scrape cancelled.');
            return;
        }

        $batchSize = $this->ask('Batch size (recommended: 50-100)', '50');
        $limit = $this->ask('Limit (0 for all bills)', '0');

        $this->info("Starting full scrape with batch size {$batchSize}...");
        
        $this->call('scrape:congress-bills', [
            '--congress' => 119,
            '--batch-size' => (int) $batchSize,
            '--limit' => (int) $limit,
            '--skip-existing' => true
        ]);
    }

    private function showScheduleStatus(): void
    {
        $this->info("Scheduled Tasks Status");
        $this->info(str_repeat('=', 30));
        
        $this->line("📅 Daily Updates: 6:00 AM");
        $this->line("   Command: scrape:congress-updates --congress=119 --days=2");
        $this->line("   Purpose: Check for new actions, cosponsors, summaries");
        
        $this->line("\n📅 Weekly Full Check: Sunday 2:00 AM");
        $this->line("   Command: scrape:congress-updates --force-all --days=7");
        $this->line("   Purpose: Comprehensive check for any missed updates");
        
        $this->line("\n📅 Daily Stats: 11:30 PM");
        $this->line("   Command: db:stats --congress=119");
        $this->line("   Purpose: Log database statistics");
        
        $this->info("\nTo start the scheduler:");
        $this->line("php artisan schedule:run");
        $this->line("Or set up a cron job: * * * * * php artisan schedule:run >> /dev/null 2>&1");
    }

    private function showHelp(): void
    {
        $this->info("\nAvailable actions:");
        $this->line("  status         - Show current database status and recent activity");
        $this->line("  update         - Run daily update check");
        $this->line("  full-scrape    - Start full scraping process");
        $this->line("  schedule-status - Show scheduled task information");
        
        $this->info("\nExamples:");
        $this->line("  php artisan congress:manage status");
        $this->line("  php artisan congress:manage update");
        $this->line("  php artisan congress:manage full-scrape");
    }
}