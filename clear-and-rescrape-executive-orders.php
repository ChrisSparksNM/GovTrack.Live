<?php

echo "=== Clear and Re-scrape Executive Orders ===" . PHP_EOL;
echo "This will clear all executive orders and re-scrape with better formatting." . PHP_EOL;
echo "" . PHP_EOL;

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExecutiveOrderScraperService;

try {
    echo "🗑️  Clearing existing executive orders..." . PHP_EOL;
    $deleted = DB::table('executive_orders')->delete();
    echo "   Deleted {$deleted} executive orders" . PHP_EOL;
    echo "" . PHP_EOL;
    
    echo "🔄 Starting fresh scrape with improved formatting..." . PHP_EOL;
    $scraper = new ExecutiveOrderScraperService();
    $stats = $scraper->scrapeExecutiveOrders(5); // Scrape first 5 pages
    
    echo "" . PHP_EOL;
    echo "📊 Scraping Results:" . PHP_EOL;
    echo "   - Pages scraped: {$stats['pages_scraped']}" . PHP_EOL;
    echo "   - Total found: {$stats['total_found']}" . PHP_EOL;
    echo "   - New orders: {$stats['new_orders']}" . PHP_EOL;
    echo "   - Updated orders: {$stats['updated_orders']}" . PHP_EOL;
    echo "   - Errors: {$stats['errors']}" . PHP_EOL;
    echo "   - Duplicates skipped: {$stats['duplicates_skipped']}" . PHP_EOL;
    
    if ($stats['new_orders'] > 0) {
        echo "" . PHP_EOL;
        echo "🎉 Success! Executive orders have been re-scraped with improved formatting." . PHP_EOL;
        echo "" . PHP_EOL;
        
        // Show a sample of the first order's content
        $firstOrder = DB::table('executive_orders')->orderBy('signed_date', 'desc')->first();
        if ($firstOrder) {
            echo "📄 Sample content from: {$firstOrder->title}" . PHP_EOL;
            echo "   Content length: " . strlen($firstOrder->content) . " characters" . PHP_EOL;
            echo "   First 200 characters:" . PHP_EOL;
            echo "   " . substr($firstOrder->content, 0, 200) . "..." . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "✅ Process completed!" . PHP_EOL;