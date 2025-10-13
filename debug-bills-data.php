<?php

echo "=== Debug Bills Data ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "1. Checking bills table..." . PHP_EOL;
    
    // Check if bills table exists
    $billsExist = DB::select("SHOW TABLES LIKE 'bills'");
    
    if (empty($billsExist)) {
        echo "❌ Bills table does not exist" . PHP_EOL;
        
        // Check what tables we do have
        $tables = DB::select("SHOW TABLES");
        echo "Available tables:" . PHP_EOL;
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            echo "  - $tableName" . PHP_EOL;
        }
    } else {
        echo "✅ Bills table exists" . PHP_EOL;
        
        $billCount = DB::table('bills')->count();
        echo "Bills in database: $billCount" . PHP_EOL;
        
        if ($billCount > 0) {
            echo "" . PHP_EOL;
            echo "2. Sample bills..." . PHP_EOL;
            
            $sampleBills = DB::table('bills')
                ->orderBy('introduced_date', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'type', 'number', 'introduced_date']);
            
            foreach ($sampleBills as $bill) {
                echo "  - {$bill->type} {$bill->number}: " . substr($bill->title, 0, 60) . "... ({$bill->introduced_date})" . PHP_EOL;
            }
            
            echo "" . PHP_EOL;
            echo "3. Bills by type..." . PHP_EOL;
            
            $billTypes = DB::table('bills')
                ->select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get();
            
            foreach ($billTypes as $type) {
                echo "  - {$type->type}: {$type->count} bills" . PHP_EOL;
            }
            
            echo "" . PHP_EOL;
            echo "4. Recent bills..." . PHP_EOL;
            
            $recentBills = DB::table('bills')
                ->where('introduced_date', '>=', '2024-01-01')
                ->count();
            
            echo "Bills introduced since 2024: $recentBills" . PHP_EOL;
        }
    }
    
    echo "" . PHP_EOL;
    echo "5. Checking tracked_bills table..." . PHP_EOL;
    
    $trackedBillsExist = DB::select("SHOW TABLES LIKE 'tracked_bills'");
    
    if (!empty($trackedBillsExist)) {
        $trackedCount = DB::table('tracked_bills')->count();
        echo "Tracked bills: $trackedCount" . PHP_EOL;
        
        if ($trackedCount > 0) {
            $sampleTracked = DB::table('tracked_bills')
                ->limit(3)
                ->get(['congress_id', 'title', 'latest_action_date']);
            
            echo "Sample tracked bills:" . PHP_EOL;
            foreach ($sampleTracked as $bill) {
                echo "  - {$bill->congress_id}: " . substr($bill->title, 0, 60) . "... ({$bill->latest_action_date})" . PHP_EOL;
            }
        }
    } else {
        echo "❌ Tracked bills table does not exist" . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    echo "6. Testing chatbot with bill question..." . PHP_EOL;
    
    $chatbotService = app('App\Services\CongressChatbotService');
    $result = $chatbotService->askQuestion('How many bills are currently in Congress?');
    
    echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
    echo "Success: " . ($result['success'] ? 'true' : 'false') . PHP_EOL;
    
    if (isset($result['queries'])) {
        echo "Queries executed: " . count($result['queries']) . PHP_EOL;
        foreach ($result['queries'] as $i => $query) {
            echo "  Query " . ($i + 1) . ": " . substr($query, 0, 100) . "..." . PHP_EOL;
        }
    }
    
    if (isset($result['query_results'])) {
        echo "Query results: " . count($result['query_results']) . " result sets" . PHP_EOL;
        foreach ($result['query_results'] as $i => $queryResult) {
            if (is_array($queryResult)) {
                echo "  Result " . ($i + 1) . ": " . count($queryResult) . " records" . PHP_EOL;
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
}