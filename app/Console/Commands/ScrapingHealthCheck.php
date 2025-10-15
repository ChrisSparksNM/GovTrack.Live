<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\ExecutiveOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScrapingHealthCheck extends Command
{
    protected $signature = 'scraping:health-check 
                            {--detailed : Show detailed statistics}
                            {--alerts : Show only alerts and issues}';

    protected $description = 'Check the health and status of scraping operations';

    public function handle(): int
    {
        $this->info('🏥 Scraping Health Check');
        $this->info('Time: ' . now()->format('Y-m-d H:i:s'));
        $this->info('========================');

        $issues = [];
        $warnings = [];

        // Check Executive Orders
        $this->checkExecutiveOrders($issues, $warnings);
        
        // Check Bills
        $this->checkBills($issues, $warnings);
        
        // Check Scraping Logs
        $this->checkScrapingLogs($issues, $warnings);
        
        // Check Database Health
        $this->checkDatabaseHealth($issues, $warnings);

        // Show results
        $this->showResults($issues, $warnings);

        return empty($issues) ? Command::SUCCESS : Command::FAILURE;
    }

    private function checkExecutiveOrders(array &$issues, array &$warnings): void
    {
        $this->info('📋 Executive Orders Status:');
        
        $total = ExecutiveOrder::count();
        $recentCount = ExecutiveOrder::where('created_at', '>=', now()->subDays(7))->count();
        $fullyScraped = ExecutiveOrder::where('is_fully_scraped', true)->count();
        $lastScrapeTime = ExecutiveOrder::max('last_scraped_at');
        
        $this->line("  Total Orders: {$total}");
        $this->line("  Added Last 7 Days: {$recentCount}");
        $this->line("  Fully Scraped: {$fullyScraped}");
        $this->line("  Last Scrape: " . ($lastScrapeTime ? Carbon::parse($lastScrapeTime)->diffForHumans() : 'Never'));
        
        // Check for issues
        if ($total === 0) {
            $issues[] = 'No executive orders in database';
        }
        
        if ($lastScrapeTime && Carbon::parse($lastScrapeTime)->diffInDays() > 2) {
            $warnings[] = 'Executive orders not scraped in over 2 days';
        }
        
        if ($fullyScraped < $total * 0.8) {
            $warnings[] = 'Less than 80% of executive orders are fully scraped';
        }
        
        $this->info('');
    }

    private function checkBills(array &$issues, array &$warnings): void
    {
        $this->info('📊 Congressional Bills Status:');
        
        $total = Bill::count();
        $recentCount = Bill::where('created_at', '>=', now()->subDays(7))->count();
        $fullyScraped = Bill::where('is_fully_scraped', true)->count();
        $withText = Bill::whereNotNull('bill_text')->count();
        $lastScrapeTime = Bill::max('last_scraped_at');
        $recentUpdates = Bill::where('updated_at', '>=', now()->subDays(1))->count();
        
        $this->line("  Total Bills: {$total}");
        $this->line("  Added Last 7 Days: {$recentCount}");
        $this->line("  Updated Last 24h: {$recentUpdates}");
        $this->line("  Fully Scraped: {$fullyScraped}");
        $this->line("  With Full Text: {$withText}");
        $this->line("  Last Scrape: " . ($lastScrapeTime ? Carbon::parse($lastScrapeTime)->diffForHumans() : 'Never'));
        
        // Check for issues
        if ($total === 0) {
            $issues[] = 'No bills in database';
        }
        
        if ($lastScrapeTime && Carbon::parse($lastScrapeTime)->diffInDays() > 1) {
            $warnings[] = 'Bills not scraped in over 1 day';
        }
        
        if ($recentUpdates === 0) {
            $warnings[] = 'No bill updates in the last 24 hours';
        }
        
        if ($fullyScraped < $total * 0.5) {
            $warnings[] = 'Less than 50% of bills are fully scraped';
        }
        
        $this->info('');
    }

    private function checkScrapingLogs(array &$issues, array &$warnings): void
    {
        $this->info('📝 Scraping Logs Status:');
        
        $logFiles = [
            'executive-orders-scrape.log' => 'Executive Orders',
            'congress-updates.log' => 'Congress Updates',
            'congress-bills.log' => 'Congress Bills',
            'daily-scraping-completion.log' => 'Daily Completion'
        ];
        
        foreach ($logFiles as $file => $name) {
            $path = storage_path('logs/' . $file);
            if (file_exists($path)) {
                $lastModified = Carbon::createFromTimestamp(filemtime($path));
                $size = filesize($path);
                $this->line("  {$name}: " . $lastModified->diffForHumans() . " ({$size} bytes)");
                
                // Check for errors in recent logs
                if ($size > 0) {
                    $content = file_get_contents($path);
                    $errorCount = substr_count(strtolower($content), 'error');
                    $failCount = substr_count(strtolower($content), 'failed');
                    
                    if ($errorCount > 0 || $failCount > 0) {
                        $warnings[] = "{$name} log contains {$errorCount} errors and {$failCount} failures";
                    }
                }
            } else {
                $this->line("  {$name}: Not found");
                $warnings[] = "{$name} log file missing";
            }
        }
        
        $this->info('');
    }

    private function checkDatabaseHealth(array &$issues, array &$warnings): void
    {
        $this->info('💾 Database Health:');
        
        try {
            // Check database connection
            DB::connection()->getPdo();
            $this->line('  Connection: ✅ OK');
            
            // Check table sizes
            $billsSize = DB::table('bills')->count();
            $executiveOrdersSize = DB::table('executive_orders')->count();
            $actionsSize = DB::table('bill_actions')->count();
            $sponsorsSize = DB::table('bill_sponsors')->count();
            
            $this->line("  Bills Table: {$billsSize} records");
            $this->line("  Executive Orders Table: {$executiveOrdersSize} records");
            $this->line("  Bill Actions Table: {$actionsSize} records");
            $this->line("  Bill Sponsors Table: {$sponsorsSize} records");
            
            // Check for orphaned records
            $orphanedActions = DB::table('bill_actions')
                ->leftJoin('bills', 'bill_actions.bill_id', '=', 'bills.id')
                ->whereNull('bills.id')
                ->count();
                
            if ($orphanedActions > 0) {
                $warnings[] = "{$orphanedActions} orphaned bill actions found";
            }
            
        } catch (\Exception $e) {
            $issues[] = 'Database connection failed: ' . $e->getMessage();
        }
        
        $this->info('');
    }

    private function showResults(array $issues, array $warnings): void
    {
        if ($this->option('alerts') && empty($issues) && empty($warnings)) {
            $this->info('🎉 No issues or warnings found!');
            return;
        }

        if (!empty($issues)) {
            $this->error('🚨 CRITICAL ISSUES:');
            foreach ($issues as $issue) {
                $this->error("  ❌ {$issue}");
            }
            $this->info('');
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  WARNINGS:');
            foreach ($warnings as $warning) {
                $this->warn("  ⚠️  {$warning}");
            }
            $this->info('');
        }

        if (empty($issues) && empty($warnings)) {
            $this->info('🎉 All systems healthy!');
        }

        // Show recommendations
        if (!empty($issues) || !empty($warnings)) {
            $this->info('💡 RECOMMENDATIONS:');
            
            if (!empty($issues)) {
                $this->line('  • Run: php artisan scraping:daily --force');
                $this->line('  • Check database configuration');
                $this->line('  • Verify API keys and network connectivity');
            }
            
            if (!empty($warnings)) {
                $this->line('  • Consider running individual scrapers manually');
                $this->line('  • Check cron job configuration');
                $this->line('  • Monitor disk space and memory usage');
            }
        }
    }
}