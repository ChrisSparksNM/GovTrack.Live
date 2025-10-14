<?php

echo "=== Test Executive Orders Chatbot Integration ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will test if executive orders are properly integrated into the chatbot." . PHP_EOL;
echo "" . PHP_EOL;

// Check if we have executive orders
$totalOrders = DB::table('executive_orders')->count();
$embeddingsCount = DB::table('embeddings')->where('entity_type', 'executive_order')->count();

echo "📊 Current Status:" . PHP_EOL;
echo "   - Executive Orders: $totalOrders" . PHP_EOL;
echo "   - Executive Order Embeddings: $embeddingsCount" . PHP_EOL;

if ($totalOrders === 0) {
    echo "" . PHP_EOL;
    echo "❌ No executive orders found. Please run the scraper first:" . PHP_EOL;
    echo "   php scrape-executive-orders.php" . PHP_EOL;
    exit(1);
}

if ($embeddingsCount === 0) {
    echo "" . PHP_EOL;
    echo "⚠️  No executive order embeddings found. Generating embeddings would improve results:" . PHP_EOL;
    echo "   php embed-executive-orders.php" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Continuing with basic database search..." . PHP_EOL;
}

echo "" . PHP_EOL;

// Test semantic search service
echo "1. Testing SemanticSearchService..." . PHP_EOL;

try {
    $semanticService = app('App\Services\SemanticSearchService');
    
    // Test if executive orders are included in general search
    $generalResults = $semanticService->search('recent presidential actions', [
        'limit' => 5,
        'threshold' => 0.3
    ]);
    
    if ($generalResults['success']) {
        $executiveOrderResults = array_filter($generalResults['results'], function($result) {
            return $result['entity_type'] === 'executive_order';
        });
        
        echo "   ✅ General search found " . count($executiveOrderResults) . " executive orders" . PHP_EOL;
    } else {
        echo "   ❌ General search failed" . PHP_EOL;
    }
    
    // Test specific executive order search
    if (method_exists($semanticService, 'searchExecutiveOrders')) {
        $specificResults = $semanticService->searchExecutiveOrders('recent executive orders', [
            'limit' => 3,
            'threshold' => 0.3
        ]);
        
        if ($specificResults['success']) {
            echo "   ✅ Specific executive order search found " . count($specificResults['executive_orders']) . " orders" . PHP_EOL;
            
            foreach ($specificResults['executive_orders'] as $i => $result) {
                $order = $result['model'];
                echo "      " . ($i + 1) . ". {$order->display_name} - {$order->signed_date->format('M j, Y')}" . PHP_EOL;
            }
        } else {
            echo "   ❌ Specific executive order search failed" . PHP_EOL;
        }
    } else {
        echo "   ❌ searchExecutiveOrders method not found" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ SemanticSearchService error: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;

// Test chatbot service
echo "2. Testing CongressChatbotService..." . PHP_EOL;

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    
    $testQueries = [
        'What executive orders were signed recently?',
        'Show me recent presidential actions',
        'What did the president sign this year?'
    ];
    
    foreach ($testQueries as $i => $query) {
        echo "   Testing query " . ($i + 1) . ": '$query'" . PHP_EOL;
        
        $result = $chatbotService->askQuestion($query);
        
        if ($result['success']) {
            $method = $result['method'] ?? 'unknown';
            echo "      ✅ Success with method: $method" . PHP_EOL;
            
            // Check if response mentions executive orders
            $mentionsEO = preg_match('/\b(executive order|presidential action|EO \d+)/i', $result['response']);
            echo "      " . ($mentionsEO ? "✅" : "❌") . " Response mentions executive orders: " . ($mentionsEO ? "Yes" : "No") . PHP_EOL;
            
            // Show a preview
            $preview = substr($result['response'], 0, 150);
            echo "      Preview: $preview..." . PHP_EOL;
            
        } else {
            echo "      ❌ Failed: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
        }
        
        echo "" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ CongressChatbotService error: " . $e->getMessage() . PHP_EOL;
}

// Test database queries
echo "3. Testing direct database queries..." . PHP_EOL;

try {
    // Recent executive orders
    $recentOrders = DB::table('executive_orders')
        ->where('signed_date', '>=', now()->subDays(30))
        ->orderBy('signed_date', 'desc')
        ->limit(5)
        ->get(['title', 'order_number', 'signed_date', 'status']);
    
    echo "   Recent orders (last 30 days): " . $recentOrders->count() . PHP_EOL;
    foreach ($recentOrders as $order) {
        $displayName = $order->order_number ? "EO {$order->order_number}" : "Order";
        echo "      - $displayName: {$order->title} ({$order->signed_date})" . PHP_EOL;
    }
    
    // Orders by year
    $currentYearOrders = DB::table('executive_orders')
        ->whereYear('signed_date', now()->year)
        ->count();
    
    echo "   Orders this year: $currentYearOrders" . PHP_EOL;
    
} catch (Exception $e) {
    echo "   ❌ Database query error: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Integration Test Complete ===" . PHP_EOL;
echo "" . PHP_EOL;
echo "💡 Next steps:" . PHP_EOL;
echo "   1. If embeddings are missing, run: php embed-executive-orders.php" . PHP_EOL;
echo "   2. Test the web interface at /executive-orders" . PHP_EOL;
echo "   3. Test chatbot queries about executive orders" . PHP_EOL;
echo "   4. Check that the navbar shows 'EXECUTIVE ORDERS' link" . PHP_EOL;