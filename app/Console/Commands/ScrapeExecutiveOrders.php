<?php

namespace App\Console\Commands;

use App\Services\ExecutiveOrderScraperService;
use Illuminate\Console\Command;

class ScrapeExecutiveOrders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'executive-orders:scrape 
                            {--pages=5 : Number of pages to scrape}
                            {--stats : Show scraping statistics}';

    /**
     * The console command description.
     */
    protected $description = 'Scrape executive orders from the White House website';

    /**
     * Execute the console command.
     */
    public function handle(ExecutiveOrderScraperService $scraperService): int
    {
        if ($this->option('stats')) {
            $this->showStats($scraperService);
            return Command::SUCCESS;
        }

        $maxPages = (int) $this->option('pages');
        
        $this->info("🏛️  Starting Executive Orders Scraper");
        $this->info("📄 Will scrape up to {$maxPages} pages");
        $this->newLine();

        $startTime = microtime(true);
        
        try {
            $stats = $scraperService->scrapeExecutiveOrders($maxPages);
            
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            $this->newLine();
            $this->info("🎉 Scraping completed in {$duration} seconds");
            $this->newLine();
            
            // Display results
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Pages Scraped', $stats['pages_scraped']],
                    ['Total Found', $stats['total_found']],
                    ['New Orders', $stats['new_orders']],
                    ['Updated Orders', $stats['updated_orders']],
                    ['Errors', $stats['errors']],
                ]
            );
            
            if ($stats['errors'] > 0) {
                $this->warn("⚠️  {$stats['errors']} errors occurred during scraping. Check logs for details.");
            }
            
            // Show current database stats
            $this->newLine();
            $this->showStats($scraperService);
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Scraping failed: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
    
    /**
     * Show scraping statistics
     */
    private function showStats(ExecutiveOrderScraperService $scraperService): void
    {
        $stats = $scraperService->getScrapingStats();
        
        $this->info("📊 Executive Orders Database Statistics");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Orders', number_format($stats['total_orders'])],
                ['Fully Scraped', number_format($stats['fully_scraped'])],
                ['Needs Scraping', number_format($stats['needs_scraping'])],
                ['Recent Orders (30 days)', number_format($stats['recent_orders'])],
                ['Current Year', number_format($stats['current_year'])],
                ['Last Scraped', $stats['last_scraped'] ? $stats['last_scraped']->format('Y-m-d H:i:s') : 'Never'],
            ]
        );
    }
}