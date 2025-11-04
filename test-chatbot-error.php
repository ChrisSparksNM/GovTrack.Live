<?php

echo "=== Test Chatbot Database Query Issue ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing why the chatbot is only returning 20 bills..." . PHP_EOL;
echo "" . PHP_EOL;

// Test the chatbot service directly
try {
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "1. Testing Jefferson Van Drew question..." . PHP_EOL;
    $result = $chatbotService->askQuestion("Tell me about Jefferson Van Drew's recent congressional activity");
    
    if ($result['success']) {
        echo "   ✅ Success with method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        echo "   Response length: " . strlen($result['response']) . " characters" . PHP_EOL;
        
        // Check if response mentions only 20 bills
        if (preg_match('/\b20\s+bills?\b/i', $result['response'])) {
            echo "   ❌ Response mentions '20 bills' - this is the issue!" . PHP_EOL;
        } else {
            echo "   ✅ Response doesn't mention '20 bills'" . PHP_EOL;
        }
        
        // Show first 500 characters
        echo "   Preview: " . substr($result['response'], 0, 500) . "..." . PHP_EOL;
        
        // Check data sources
        if (isset($result['data_sources'])) {
            echo "   Data sources: " . implode(', ', $result['data_sources']) . PHP_EOL;
        }
        
    } else {
        echo "   ❌ Failed: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    
    // Test the database query service directly
    echo "2. Testing DatabaseQueryService directly..." . PHP_EOL;
    
    $databaseService = app('App\Services\DatabaseQueryService');
    $dbResult = $databaseService->queryDatabase("Tell me about Jefferson Van Drew's recent congressional activity");
    
    if ($dbResult['success']) {
        echo "   ✅ Database query successful" . PHP_EOL;
        
        if (isset($dbResult['queries'])) {
            echo "   Generated " . count($dbResult['queries']) . " queries:" . PHP_EOL;
            foreach ($dbResult['queries'] as $i => $query) {
                echo "      " . ($i + 1) . ". " . $query['description'] . PHP_EOL;
                echo "         SQL: " . substr($query['sql'], 0, 100) . "..." . PHP_EOL;
                
                // Check if SQL has LIMIT 20
                if (preg_match('/LIMIT\s+20\b/i', $query['sql'])) {
                    echo "         ❌ FOUND LIMIT 20 IN SQL!" . PHP_EOL;
                } elseif (preg_match('/LIMIT\s+(\d+)/i', $query['sql'], $matches)) {
                    echo "         ⚠️  Found LIMIT " . $matches[1] . " in SQL" . PHP_EOL;
                } else {
                    echo "         ✅ No problematic LIMIT found" . PHP_EOL;
                }
            }
        }
        
        if (isset($dbResult['results'])) {
            echo "   Query results:" . PHP_EOL;
            foreach ($dbResult['results'] as $name => $result) {
                if (isset($result['error'])) {
                    echo "      ❌ $name: " . $result['error'] . PHP_EOL;
                } else {
                    echo "      ✅ $name: " . ($result['count'] ?? 0) . " records" . PHP_EOL;
                }
            }
        }
        
    } else {
        echo "   ❌ Database query failed: " . ($dbResult['error'] ?? 'Unknown error') . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    
    // Test a simple bill count query
    echo "3. Testing simple bill count..." . PHP_EOL;
    
    $totalBills = DB::table('bills')->count();
    echo "   Total bills in database: $totalBills" . PHP_EOL;
    
    $recentBills = DB::table('bills')
        ->where('introduced_date', '>=', now()->subMonths(6))
        ->count();
    echo "   Recent bills (last 6 months): $recentBills" . PHP_EOL;
    
    // Check for Jefferson Van Drew specifically
    $vanDrewBills = DB::table('bills')
        ->join('bill_sponsors', 'bills.id', '=', 'bill_sponsors.bill_id')
        ->where('bill_sponsors.full_name', 'like', '%Van Drew%')
        ->count();
    echo "   Bills sponsored by Van Drew: $vanDrewBills" . PHP_EOL;
    
    if ($vanDrewBills > 0) {
        $vanDrewBillsList = DB::table('bills')
            ->join('bill_sponsors', 'bills.id', '=', 'bill_sponsors.bill_id')
            ->where('bill_sponsors.full_name', 'like', '%Van Drew%')
            ->select('bills.congress_id', 'bills.title', 'bills.introduced_date')
            ->orderBy('bills.introduced_date', 'desc')
            ->limit(5)
            ->get();
            
        echo "   Recent Van Drew bills:" . PHP_EOL;
        foreach ($vanDrewBillsList as $bill) {
            echo "      - {$bill->congress_id}: " . substr($bill->title, 0, 80) . "..." . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Test Complete ===" . PHP_EOL;