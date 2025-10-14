<?php

echo "=== Diagnose Data Currency Issues ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Checking what data you actually have..." . PHP_EOL;
echo "" . PHP_EOL;

// Check bill dates
echo "1. Bill Date Analysis:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

$billsByYear = DB::table('bills')
    ->selectRaw('YEAR(introduced_date) as year, COUNT(*) as count')
    ->whereNotNull('introduced_date')
    ->groupBy('year')
    ->orderBy('year', 'desc')
    ->limit(10)
    ->get();

echo "Bills by year (most recent first):" . PHP_EOL;
foreach ($billsByYear as $yearData) {
    echo "  {$yearData->year}: {$yearData->count} bills" . PHP_EOL;
}

// Check recent bills
$recentBills = DB::table('bills')
    ->where('introduced_date', '>=', '2024-01-01')
    ->orderBy('introduced_date', 'desc')
    ->limit(5)
    ->get(['id', 'type', 'number', 'title', 'introduced_date']);

echo "" . PHP_EOL;
echo "Recent bills (2024+):" . PHP_EOL;
if ($recentBills->count() > 0) {
    foreach ($recentBills as $bill) {
        echo "  {$bill->type} {$bill->number}: " . substr($bill->title, 0, 60) . "... ({$bill->introduced_date})" . PHP_EOL;
    }
} else {
    echo "  ❌ No bills from 2024 found!" . PHP_EOL;
}

echo "" . PHP_EOL;

// Check member data currency
echo "2. Member Data Analysis:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

$currentMembers = DB::table('members')
    ->where('current_member', true)
    ->count();

$totalMembers = DB::table('members')->count();

echo "Current members: $currentMembers / $totalMembers" . PHP_EOL;

// Check for recent sponsors
$recentSponsors = DB::table('bills')
    ->join('bill_sponsors', 'bills.id', '=', 'bill_sponsors.bill_id')
    ->where('bills.introduced_date', '>=', '2024-01-01')
    ->select('bill_sponsors.full_name', DB::raw('COUNT(*) as bill_count'))
    ->groupBy('bill_sponsors.full_name')
    ->orderBy('bill_count', 'desc')
    ->limit(10)
    ->get();

echo "" . PHP_EOL;
echo "Most active sponsors in 2024:" . PHP_EOL;
if ($recentSponsors->count() > 0) {
    foreach ($recentSponsors as $sponsor) {
        echo "  {$sponsor->full_name}: {$sponsor->bill_count} bills" . PHP_EOL;
    }
} else {
    echo "  ❌ No sponsor data for 2024 found!" . PHP_EOL;
}

echo "" . PHP_EOL;

// Check embeddings for recent data
echo "3. Embedding Analysis:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

$totalEmbeddings = DB::table('embeddings')->count();
$billEmbeddings = DB::table('embeddings')->where('entity_type', 'bill')->count();
$memberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();

echo "Total embeddings: $totalEmbeddings" . PHP_EOL;
echo "Bill embeddings: $billEmbeddings" . PHP_EOL;
echo "Member embeddings: $memberEmbeddings" . PHP_EOL;

// Test semantic search for recent activity
echo "" . PHP_EOL;
echo "4. Testing Semantic Search:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

try {
    $semanticService = app('App\Services\SemanticSearchService');
    
    // Test search for recent sponsors
    $results = $semanticService->searchBills('recent sponsor active 2024', ['limit' => 5]);
    
    if ($results['success'] && count($results['bills']) > 0) {
        echo "✅ Semantic search found " . count($results['bills']) . " results for 'recent sponsor active 2024':" . PHP_EOL;
        foreach ($results['bills'] as $i => $result) {
            $bill = $result['model'];
            echo "  " . ($i + 1) . ". {$bill->type} {$bill->number}: " . substr($bill->title, 0, 50) . "... ({$bill->introduced_date})" . PHP_EOL;
        }
    } else {
        echo "❌ Semantic search not finding recent results" . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    
    // Test search for specific recent terms
    $recentResults = $semanticService->searchBills('2024 introduced', ['limit' => 3]);
    
    if ($recentResults['success'] && count($recentResults['bills']) > 0) {
        echo "✅ Found " . count($recentResults['bills']) . " results for '2024 introduced':" . PHP_EOL;
        foreach ($recentResults['bills'] as $i => $result) {
            $bill = $result['model'];
            echo "  " . ($i + 1) . ". {$bill->type} {$bill->number} ({$bill->introduced_date})" . PHP_EOL;
        }
    } else {
        echo "❌ No results for '2024 introduced'" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error testing semantic search: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;

// Check database query approach
echo "5. Testing Database Query Approach:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

try {
    // Test direct database query for recent active sponsors
    $activeSponsors = DB::table('bills')
        ->join('bill_sponsors', 'bills.id', '=', 'bill_sponsors.bill_id')
        ->where('bills.introduced_date', '>=', '2023-01-01') // Broader date range
        ->select('bill_sponsors.full_name', 'bill_sponsors.party', DB::raw('COUNT(*) as bill_count'))
        ->groupBy('bill_sponsors.full_name', 'bill_sponsors.party')
        ->orderBy('bill_count', 'desc')
        ->limit(10)
        ->get();
    
    if ($activeSponsors->count() > 0) {
        echo "✅ Found active sponsors (2023+):" . PHP_EOL;
        foreach ($activeSponsors as $sponsor) {
            echo "  {$sponsor->full_name} ({$sponsor->party}): {$sponsor->bill_count} bills" . PHP_EOL;
        }
    } else {
        echo "❌ No active sponsors found even with broader date range" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error testing database query: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "6. Recommendations:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

$recentBillCount = DB::table('bills')->where('introduced_date', '>=', '2024-01-01')->count();
$recent2023Count = DB::table('bills')->where('introduced_date', '>=', '2023-01-01')->count();

if ($recentBillCount == 0) {
    echo "🚨 ISSUE: No bills from 2024 in your database!" . PHP_EOL;
    echo "   Your data appears to be historical only." . PHP_EOL;
    echo "   You need to scrape more recent congressional data." . PHP_EOL;
} elseif ($recentBillCount < 100) {
    echo "⚠️  LIMITED: Only $recentBillCount bills from 2024" . PHP_EOL;
    echo "   Consider scraping more recent data for better results." . PHP_EOL;
} else {
    echo "✅ DATA OK: $recentBillCount bills from 2024" . PHP_EOL;
    echo "   The issue might be in search parameters or embedding content." . PHP_EOL;
}

if ($recent2023Count > 0) {
    echo "💡 SUGGESTION: You have $recent2023Count bills from 2023+" . PHP_EOL;
    echo "   The chatbot should be able to find recent activity." . PHP_EOL;
    echo "   The issue might be in how the search is configured." . PHP_EOL;
}

echo "" . PHP_EOL;
echo "Next steps:" . PHP_EOL;
echo "1. If no recent data: Scrape more current congressional data" . PHP_EOL;
echo "2. If recent data exists: Adjust search parameters to prioritize recent results" . PHP_EOL;
echo "3. If embeddings are missing: Run the resume embedding script" . PHP_EOL;