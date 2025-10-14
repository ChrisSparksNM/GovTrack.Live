<?php

echo "=== Executive Orders Scraper ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExecutiveOrderScraperService;

echo "This will scrape executive orders from the White House website." . PHP_EOL;
echo "URL: https://www.whitehouse.gov/presidential-actions/executive-orders/" . PHP_EOL;
echo "" . PHP_EOL;

// Check if the table exists
try {
    $tableExists = DB::getSchemaBuilder()->hasTable('executive_orders');
    if (!$tableExists) {
        echo "❌ Executive orders table doesn't exist. Please run the migration first:" . PHP_EOL;
        echo "   php artisan migrate" . PHP_EOL;
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Show current stats
$scraperService = new ExecutiveOrderScraperService();
$stats = $scraperService->getScrapingStats();

echo "📊 Current Database Status:" . PHP_EOL;
echo "   - Total Orders: " . number_format($stats['total_orders']) . PHP_EOL;
echo "   - Fully Scraped: " . number_format($stats['fully_scraped']) . PHP_EOL;
echo "   - Needs Scraping: " . number_format($stats['needs_scraping']) . PHP_EOL;
echo "   - Recent Orders (30 days): " . number_format($stats['recent_orders']) . PHP_EOL;
echo "   - Current Year: " . number_format($stats['current_year']) . PHP_EOL;
echo "   - Last Scraped: " . ($stats['last_scraped'] ? $stats['last_scraped']->format('Y-m-d H:i:s') : 'Never') . PHP_EOL;
echo "" . PHP_EOL;

// Ask for confirmation
echo "How many pages would you like to scrape? (default: 5): ";
$handle = fopen("php://stdin", "r");
$pagesInput = trim(fgets($handle));
fclose($handle);

$maxPages = empty($pagesInput) ? 5 : (int) $pagesInput;

if ($maxPages <= 0) {
    echo "Invalid number of pages. Exiting." . PHP_EOL;
    exit(1);
}

echo "" . PHP_EOL;
echo "🚀 Starting scraper for {$maxPages} pages..." . PHP_EOL;
echo "⏱️  This may take several minutes depending on the number of pages." . PHP_EOL;
echo "" . PHP_EOL;

$startTime = microtime(true);

try {
    $results = $scraperService->scrapeExecutiveOrders($maxPages);
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "" . PHP_EOL;
    echo "=== Scraping Results ===" . PHP_EOL;
    echo "⏱️  Duration: {$duration} seconds" . PHP_EOL;
    echo "📄 Pages Scraped: {$results['pages_scraped']}" . PHP_EOL;
    echo "🔍 Total Found: {$results['total_found']}" . PHP_EOL;
    echo "✅ New Orders: {$results['new_orders']}" . PHP_EOL;
    echo "🔄 Updated Orders: {$results['updated_orders']}" . PHP_EOL;
    echo "❌ Errors: {$results['errors']}" . PHP_EOL;
    
    if ($results['errors'] > 0) {
        echo "" . PHP_EOL;
        echo "⚠️  Some errors occurred during scraping. Check the Laravel logs for details." . PHP_EOL;
    }
    
    // Show updated stats
    echo "" . PHP_EOL;
    echo "📊 Updated Database Status:" . PHP_EOL;
    $newStats = $scraperService->getScrapingStats();
    echo "   - Total Orders: " . number_format($newStats['total_orders']) . PHP_EOL;
    echo "   - Fully Scraped: " . number_format($newStats['fully_scraped']) . PHP_EOL;
    echo "   - Recent Orders (30 days): " . number_format($newStats['recent_orders']) . PHP_EOL;
    echo "   - Current Year: " . number_format($newStats['current_year']) . PHP_EOL;
    
    if ($newStats['total_orders'] > 0) {
        echo "" . PHP_EOL;
        echo "🎉 Success! Executive orders have been scraped and stored in the database." . PHP_EOL;
        echo "" . PHP_EOL;
        echo "💡 Next steps:" . PHP_EOL;
        echo "   1. Generate embeddings for the executive orders" . PHP_EOL;
        echo "   2. Update your chatbot to include executive orders in searches" . PHP_EOL;
        echo "   3. Set up a cron job to run this scraper regularly" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "" . PHP_EOL;
    echo "❌ Scraping failed: " . $e->getMessage() . PHP_EOL;
    echo "" . PHP_EOL;
    echo "💡 Troubleshooting tips:" . PHP_EOL;
    echo "   1. Check your internet connection" . PHP_EOL;
    echo "   2. Verify the White House website is accessible" . PHP_EOL;
    echo "   3. Check Laravel logs for detailed error information" . PHP_EOL;
    echo "   4. Try running with fewer pages first" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "📝 You can also run this via Artisan command:" . PHP_EOL;
echo "   php artisan executive-orders:scrape --pages={$maxPages}" . PHP_EOL;
echo "   php artisan executive-orders:scrape --stats" . PHP_EOL;