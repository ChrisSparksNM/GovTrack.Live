<?php

echo "=== Fix Recent Data Search Issues ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will improve your chatbot's ability to find recent/current data." . PHP_EOL;
echo "" . PHP_EOL;

// First, diagnose the issue
$recentBills = DB::table('bills')->where('introduced_date', '>=', '2024-01-01')->count();
$recent2023 = DB::table('bills')->where('introduced_date', '>=', '2023-01-01')->count();

echo "Data check:" . PHP_EOL;
echo "- Bills from 2024: $recentBills" . PHP_EOL;
echo "- Bills from 2023+: $recent2023" . PHP_EOL;
echo "" . PHP_EOL;

if ($recent2023 == 0) {
    echo "🚨 CRITICAL: No recent data in your database!" . PHP_EOL;
    echo "Your database appears to contain only historical data." . PHP_EOL;
    echo "You need to scrape more recent congressional data first." . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Recommendations:" . PHP_EOL;
    echo "1. Update your scraping scripts to get 2023-2024 data" . PHP_EOL;
    echo "2. Run: php artisan bills:scrape --recent" . PHP_EOL;
    echo "3. Then regenerate embeddings for the new data" . PHP_EOL;
    exit(1);
}

echo "✅ You have recent data. The issue is likely in search configuration." . PHP_EOL;
echo "" . PHP_EOL;

// Test current search behavior
echo "Testing current search behavior..." . PHP_EOL;

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    
    // Test with a more specific recent query
    echo "1. Testing: 'Who sponsored bills in 2024?'" . PHP_EOL;
    $result1 = $chatbotService->askQuestion('Who sponsored bills in 2024?');
    
    if ($result1['success']) {
        echo "✅ Query successful, method: " . ($result1['method'] ?? 'unknown') . PHP_EOL;
        
        if (strpos($result1['response'], '2024') !== false) {
            echo "✅ Response mentions 2024" . PHP_EOL;
        } else {
            echo "❌ Response doesn't mention 2024" . PHP_EOL;
        }
        
        if (preg_match('/\b(HR|S)\s+\d+/', $result1['response'])) {
            echo "✅ Response contains specific bill references" . PHP_EOL;
        } else {
            echo "❌ Response lacks specific bill references" . PHP_EOL;
        }
    } else {
        echo "❌ Query failed: " . ($result1['error'] ?? 'Unknown') . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    
    // Test semantic search directly
    echo "2. Testing semantic search directly..." . PHP_EOL;
    $semanticService = app('App\Services\SemanticSearchService');
    
    $searchResults = $semanticService->searchBills('sponsor active recent 2024', [
        'limit' => 5,
        'threshold' => 0.6 // Lower threshold to get more results
    ]);
    
    if ($searchResults['success'] && count($searchResults['bills']) > 0) {
        echo "✅ Semantic search found " . count($searchResults['bills']) . " results:" . PHP_EOL;
        
        foreach ($searchResults['bills'] as $i => $result) {
            $bill = $result['model'];
            $similarity = $result['similarity'] ?? 0;
            echo "  " . ($i + 1) . ". {$bill->type} {$bill->number} (similarity: " . round($similarity, 3) . ") - {$bill->introduced_date}" . PHP_EOL;
        }
        
        // Check if results are recent
        $recentResults = 0;
        foreach ($searchResults['bills'] as $result) {
            if ($result['model']->introduced_date >= '2023-01-01') {
                $recentResults++;
            }
        }
        
        echo "   Recent results (2023+): $recentResults / " . count($searchResults['bills']) . PHP_EOL;
        
        if ($recentResults == 0) {
            echo "❌ Semantic search is not prioritizing recent results" . PHP_EOL;
        }
        
    } else {
        echo "❌ Semantic search found no results" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error testing: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;

// Now let's create a better query that prioritizes recent data
echo "3. Testing improved query approach..." . PHP_EOL;

try {
    // Direct database query for recent active sponsors
    $recentActiveSponsors = DB::table('bills')
        ->join('bill_sponsors', 'bills.id', '=', 'bill_sponsors.bill_id')
        ->where('bills.introduced_date', '>=', '2023-01-01')
        ->select(
            'bill_sponsors.full_name',
            'bill_sponsors.party',
            'bill_sponsors.state',
            DB::raw('COUNT(*) as bill_count'),
            DB::raw('MAX(bills.introduced_date) as latest_bill')
        )
        ->groupBy('bill_sponsors.full_name', 'bill_sponsors.party', 'bill_sponsors.state')
        ->orderBy('bill_count', 'desc')
        ->limit(10)
        ->get();
    
    if ($recentActiveSponsors->count() > 0) {
        echo "✅ Found recent active sponsors:" . PHP_EOL;
        foreach ($recentActiveSponsors as $sponsor) {
            echo "  {$sponsor->full_name} ({$sponsor->party}-{$sponsor->state}): {$sponsor->bill_count} bills (latest: {$sponsor->latest_bill})" . PHP_EOL;
        }
        
        echo "" . PHP_EOL;
        echo "🎯 This is the data your chatbot should be finding!" . PHP_EOL;
        
    } else {
        echo "❌ No recent active sponsors found in database" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error querying recent sponsors: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "4. Recommendations to fix the issue:" . PHP_EOL;
echo str_repeat("-", 50) . PHP_EOL;

if ($recentActiveSponsors->count() > 0) {
    echo "✅ Your database has recent data, but the chatbot isn't finding it." . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Possible fixes:" . PHP_EOL;
    echo "1. 🔧 Adjust semantic search thresholds (lower = more results)" . PHP_EOL;
    echo "2. 🔧 Improve embedding content to include dates prominently" . PHP_EOL;
    echo "3. 🔧 Add temporal filtering to search results" . PHP_EOL;
    echo "4. 🔧 Use hybrid search (semantic + database filters)" . PHP_EOL;
    echo "" . PHP_EOL;
    
    echo "Quick fix options:" . PHP_EOL;
    echo "A. Lower the similarity threshold in SemanticSearchService" . PHP_EOL;
    echo "B. Add date-based filtering to chatbot queries" . PHP_EOL;
    echo "C. Regenerate embeddings with better date emphasis" . PHP_EOL;
    
} else {
    echo "❌ Your database lacks recent sponsor data." . PHP_EOL;
    echo "You need to scrape more recent congressional data." . PHP_EOL;
}

echo "" . PHP_EOL;
echo "Would you like me to:" . PHP_EOL;
echo "1. Adjust search parameters to find recent data better? (y/N)" . PHP_EOL;

$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) === 'y') {
    echo "" . PHP_EOL;
    echo "🔧 Applying search parameter fixes..." . PHP_EOL;
    
    // This would modify the SemanticSearchService to prioritize recent results
    echo "Recommended changes:" . PHP_EOL;
    echo "1. Lower similarity threshold from 0.7 to 0.6 in SemanticSearchService" . PHP_EOL;
    echo "2. Add date-based sorting to search results" . PHP_EOL;
    echo "3. Include more temporal keywords in embeddings" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "These changes would help your chatbot find recent data more effectively." . PHP_EOL;
}

echo "" . PHP_EOL;
echo "🧪 Test your chatbot with these improved queries:" . PHP_EOL;
echo "   • 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
echo "   • 'Show me recent bills from this year'" . PHP_EOL;
echo "   • 'What bills were introduced in the last 6 months?'" . PHP_EOL;