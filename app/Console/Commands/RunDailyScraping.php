<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;

class RunDailyScraping extends Command
{
    protected $signature = 'scraping:daily 
                            {--executive-orders : Run only executive orders scraping}
                            {--congress-updates : Run only congress updates scraping}
                            {--congress-bills : Run only congress bills scraping}
                            {--all : Run all scrapers (default)}
                            {--dry-run : Show what would be run without executing}
                            {--force : Force run even if already ran today}';

    protected $description = 'Run daily scraping operations for all data sources';

    public function handle(): int
    {
        $this->info('🚀 Starting Daily Scraping Operations');
        $this->info('Time: ' . now()->format('Y-m-d H:i:s'));
        
        $startTime = microtime(true);
        $results = [];

        // Check what to run
        $runExecutiveOrders = $this->option('executive-orders') || $this->option('all') || (!$this->option('congress-updates') && !$this->option('congress-bills'));
        $runCongressUpdates = $this->option('congress-updates') || $this->option('all') || (!$this->option('executive-orders') && !$this->option('congress-bills'));
        $runCongressBills = $this->option('congress-bills') || $this->option('all') || (!$this->option('executive-orders') && !$this->option('congress-updates'));

        if ($this->option('dry-run')) {
            $this->warn('🔍 DRY RUN MODE - No actual scraping will be performed');
            $this->showPlan($runExecutiveOrders, $runCongressUpdates, $runCongressBills);
            return Command::SUCCESS;
        }

        // Check if already ran today (unless forced)
        if (!$this->option('force') && $this->hasRunToday()) {
            $this->warn('⚠️  Daily scraping has already been completed today. Use --force to run again.');
            return Command::SUCCESS;
        }

        try {
            // 1. Executive Orders Scraping
            if ($runExecutiveOrders) {
                $results['executive_orders'] = $this->runExecutiveOrdersScraping();
            }

            // 2. Congress Updates Scraping
            if ($runCongressUpdates) {
                $results['congress_updates'] = $this->runCongressUpdatesScraping();
            }

            // 3. Congress Bills Scraping
            if ($runCongressBills) {
                $results['congress_bills'] = $this->runCongressBillsScraping();
            }

            // 4. Post-scraping tasks
            $this->runPostScrapingTasks();

            // Record completion
            $this->recordCompletion();

            // Show summary
            $this->showSummary($results, microtime(true) - $startTime);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Daily scraping failed: ' . $e->getMessage());
            Log::error('Daily scraping failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function showPlan(bool $runExecutiveOrders, bool $runCongressUpdates, bool $runCongressBills): void
    {
        $this->info('📋 Scraping Plan:');
        
        if ($runExecutiveOrders) {
            $this->line('  ✓ Executive Orders (10 pages)');
        }
        
        if ($runCongressUpdates) {
            $this->line('  ✓ Congress Updates (last 7 days)');
        }
        
        if ($runCongressBills) {
            $this->line('  ✓ New Congress Bills (up to 500 bills)');
        }
        
        $this->line('  ✓ Post-scraping maintenance tasks');
    }

    private function hasRunToday(): bool
    {
        $logFile = storage_path('logs/daily-scraping-completion.log');
        if (!file_exists($logFile)) {
            return false;
        }

        $lastRun = file_get_contents($logFile);
        return Carbon::parse($lastRun)->isToday();
    }

    private function recordCompletion(): void
    {
        $logFile = storage_path('logs/daily-scraping-completion.log');
        file_put_contents($logFile, now()->toISOString());
    }

    private function runExecutiveOrdersScraping(): array
    {
        $this->info('📋 Starting Executive Orders scraping...');
        
        $exitCode = Artisan::call('executive-orders:scrape', [
            '--pages' => 10
        ]);

        $output = Artisan::output();
        
        if ($exitCode === 0) {
            $this->info('✅ Executive Orders scraping completed successfully');
        } else {
            $this->error('❌ Executive Orders scraping failed');
        }

        return [
            'success' => $exitCode === 0,
            'output' => $output,
            'exit_code' => $exitCode
        ];
    }

    private function runCongressUpdatesScraping(): array
    {
        $this->info('🔄 Starting Congress Updates scraping...');
        
        $exitCode = Artisan::call('scrape:congress-updates', [
            '--days' => 7,
            '--batch-size' => 100
        ]);

        $output = Artisan::output();
        
        if ($exitCode === 0) {
            $this->info('✅ Congress Updates scraping completed successfully');
        } else {
            $this->error('❌ Congress Updates scraping failed');
        }

        return [
            'success' => $exitCode === 0,
            'output' => $output,
            'exit_code' => $exitCode
        ];
    }

    private function runCongressBillsScraping(): array
    {
        $this->info('📊 Starting Congress Bills scraping...');
        
        $exitCode = Artisan::call('scrape:congress-bills', [
            '--limit' => 500,
            '--batch-size' => 50,
            '--skip-existing' => true
        ]);

        $output = Artisan::output();
        
        if ($exitCode === 0) {
            $this->info('✅ Congress Bills scraping completed successfully');
        } else {
            $this->error('❌ Congress Bills scraping failed');
        }

        return [
            'success' => $exitCode === 0,
            'output' => $output,
            'exit_code' => $exitCode
        ];
    }

    private function runPostScrapingTasks(): void
    {
        $this->info('🧹 Running post-scraping maintenance tasks...');

        // Generate embeddings for new content
        try {
            Artisan::call('generate:embeddings', ['--limit' => 100]);
            $this->info('✅ Embeddings generation completed');
        } catch (\Exception $e) {
            $this->warn('⚠️  Embeddings generation failed: ' . $e->getMessage());
        }

        // Clear old cache
        try {
            Artisan::call('cache:clear');
            $this->info('✅ Cache cleared');
        } catch (\Exception $e) {
            $this->warn('⚠️  Cache clearing failed: ' . $e->getMessage());
        }

        // Update database statistics
        try {
            Artisan::call('show:database-stats');
            $this->info('✅ Database statistics updated');
        } catch (\Exception $e) {
            $this->warn('⚠️  Database statistics update failed: ' . $e->getMessage());
        }
    }

    private function showSummary(array $results, float $totalTime): void
    {
        $this->info('');
        $this->info('📊 Daily Scraping Summary');
        $this->info('========================');
        $this->info('Total Time: ' . round($totalTime, 2) . ' seconds');
        $this->info('Completed: ' . now()->format('Y-m-d H:i:s'));
        $this->info('');

        foreach ($results as $task => $result) {
            $status = $result['success'] ? '✅' : '❌';
            $taskName = ucwords(str_replace('_', ' ', $task));
            $this->info("{$status} {$taskName}: " . ($result['success'] ? 'Success' : 'Failed'));
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);
        
        $this->info('');
        $this->info("Overall: {$successCount}/{$totalCount} tasks completed successfully");
        
        if ($successCount === $totalCount) {
            $this->info('🎉 All scraping operations completed successfully!');
        } else {
            $this->warn('⚠️  Some scraping operations failed. Check logs for details.');
        }
    }
}