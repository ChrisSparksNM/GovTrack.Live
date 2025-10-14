<?php

echo "=== Executive Orders Embedding Generator ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ExecutiveOrderEmbeddingService;

echo "This will generate embeddings for executive orders to enable AI search." . PHP_EOL;
echo "" . PHP_EOL;

// Check if we have executive orders
$totalOrders = DB::table('executive_orders')->count();
$fullyScraped = DB::table('executive_orders')->where('is_fully_scraped', true)->count();

if ($totalOrders === 0) {
    echo "❌ No executive orders found in database." . PHP_EOL;
    echo "   Please run the scraper first: php scrape-executive-orders.php" . PHP_EOL;
    exit(1);
}

if ($fullyScraped === 0) {
    echo "❌ No fully scraped executive orders found." . PHP_EOL;
    echo "   Please run the scraper to get full content first." . PHP_EOL;
    exit(1);
}

// Get the embedding service
try {
    $embeddingService = new ExecutiveOrderEmbeddingService(app('App\Services\EmbeddingService'));
} catch (Exception $e) {
    echo "❌ Could not load embedding service: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Show current stats
$stats = $embeddingService->getEmbeddingStats();

echo "📊 Current Status:" . PHP_EOL;
echo "   - Total Orders: " . number_format($stats['total_orders']) . PHP_EOL;
echo "   - Fully Scraped: " . number_format($stats['fully_scraped']) . PHP_EOL;
echo "   - Embeddings Generated: " . number_format($stats['embeddings_generated']) . PHP_EOL;
echo "   - Missing Embeddings: " . number_format($stats['missing_embeddings']) . PHP_EOL;
echo "   - Recent Orders (30 days): " . number_format($stats['recent_orders']) . PHP_EOL;
echo "   - Current Year: " . number_format($stats['current_year_orders']) . PHP_EOL;
echo "" . PHP_EOL;

if ($stats['missing_embeddings'] === 0) {
    echo "✅ All executive orders already have embeddings!" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "1. Regenerate all embeddings (y)" . PHP_EOL;
    echo "2. Generate embeddings for recent orders only (r)" . PHP_EOL;
    echo "3. Exit (N)" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Choose an option (y/r/N): ";
} else {
    echo "⚠️  {$stats['missing_embeddings']} executive orders need embeddings." . PHP_EOL;
    echo "⏱️  Estimated time: " . ceil($stats['missing_embeddings'] / 10) . " minutes" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "1. Generate embeddings for all orders (y)" . PHP_EOL;
    echo "2. Generate embeddings for recent orders only (r)" . PHP_EOL;
    echo "3. Exit (N)" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Choose an option (y/r/N): ";
}

$handle = fopen("php://stdin", "r");
$choice = trim(strtolower(fgets($handle)));
fclose($handle);

if ($choice === 'n' || empty($choice)) {
    echo "Cancelled." . PHP_EOL;
    exit(0);
}

echo "" . PHP_EOL;

$startTime = microtime(true);

try {
    if ($choice === 'r') {
        echo "🚀 Generating embeddings for recent executive orders..." . PHP_EOL;
        $results = $embeddingService->embedRecentExecutiveOrders(30);
    } else {
        echo "🚀 Generating embeddings for all executive orders..." . PHP_EOL;
        $results = $embeddingService->embedAllExecutiveOrders();
    }
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    
    echo "" . PHP_EOL;
    echo "=== Embedding Generation Results ===" . PHP_EOL;
    echo "⏱️  Duration: {$duration} seconds" . PHP_EOL;
    echo "🔄 Processed: {$results['processed']}" . PHP_EOL;
    echo "✅ Successful: {$results['success']}" . PHP_EOL;
    echo "❌ Failed: {$results['failed']}" . PHP_EOL;
    
    if ($results['failed'] > 0) {
        echo "" . PHP_EOL;
        echo "⚠️  Some embeddings failed to generate. Check the Laravel logs for details." . PHP_EOL;
    }
    
    // Show updated stats
    echo "" . PHP_EOL;
    echo "📊 Updated Status:" . PHP_EOL;
    $newStats = $embeddingService->getEmbeddingStats();
    echo "   - Total Embeddings: " . number_format($newStats['embeddings_generated']) . PHP_EOL;
    echo "   - Missing Embeddings: " . number_format($newStats['missing_embeddings']) . PHP_EOL;
    
    if ($newStats['embeddings_generated'] > 0) {
        echo "" . PHP_EOL;
        echo "🎉 Success! Executive order embeddings have been generated." . PHP_EOL;
        echo "" . PHP_EOL;
        echo "💡 Next steps:" . PHP_EOL;
        echo "   1. Update your SemanticSearchService to include 'executive_order' entity type" . PHP_EOL;
        echo "   2. Update your chatbot to search executive orders" . PHP_EOL;
        echo "   3. Test queries like 'What executive orders were signed recently?'" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "" . PHP_EOL;
    echo "❌ Embedding generation failed: " . $e->getMessage() . PHP_EOL;
    echo "" . PHP_EOL;
    echo "💡 Troubleshooting tips:" . PHP_EOL;
    echo "   1. Check your Voyage AI API key configuration" . PHP_EOL;
    echo "   2. Verify you have internet connectivity" . PHP_EOL;
    echo "   3. Check Laravel logs for detailed error information" . PHP_EOL;
    echo "   4. Try generating embeddings for recent orders only first" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "📝 You can also run this via Artisan command:" . PHP_EOL;
echo "   php artisan make:command EmbedExecutiveOrders" . PHP_EOL;