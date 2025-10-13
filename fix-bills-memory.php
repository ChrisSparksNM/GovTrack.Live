<?php

echo "=== Fix Bills Memory Issue ===" . PHP_EOL;

ini_set('memory_limit', '1024M');
echo "Memory limit set to: " . ini_get('memory_limit') . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "Starting memory: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    echo "" . PHP_EOL;
    echo "1. Checking bills table safely..." . PHP_EOL;
    
    // Check if bills table exists without loading data
    $billsExist = DB::select("SHOW TABLES LIKE 'bills'");
    
    if (empty($billsExist)) {
        echo "❌ Bills table does not exist" . PHP_EOL;
        
        // Check tracked_bills instead
        $trackedBillsExist = DB::select("SHOW TABLES LIKE 'tracked_bills'");
        if (!empty($trackedBillsExist)) {
            echo "✅ Using tracked_bills table instead" . PHP_EOL;
            $billCount = DB::table('tracked_bills')->count();
            echo "Tracked bills count: $billCount" . PHP_EOL;
        } else {
            echo "❌ No bill tables available" . PHP_EOL;
            exit;
        }
    } else {
        echo "✅ Bills table exists" . PHP_EOL;
        $billCount = DB::table('bills')->count();
        echo "Bills count: $billCount" . PHP_EOL;
    }
    
    echo "Memory after table check: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    echo "" . PHP_EOL;
    echo "2. Testing memory-safe bill query..." . PHP_EOL;
    
    // Test a simple count query that shouldn't use much memory
    if (!empty($billsExist)) {
        $recentCount = DB::table('bills')
            ->where('introduced_date', '>=', '2024-01-01')
            ->count();
        echo "Recent bills (2024+): $recentCount" . PHP_EOL;
    } else {
        $recentCount = DB::table('tracked_bills')
            ->where('latest_action_date', '>=', '2024-01-01')
            ->count();
        echo "Recent tracked bills (2024+): $recentCount" . PHP_EOL;
    }
    
    echo "Memory after count query: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    echo "" . PHP_EOL;
    echo "3. Testing chatbot with memory monitoring..." . PHP_EOL;
    
    // Monitor memory during chatbot execution
    $chatbotService = app('App\Services\CongressChatbotService');
    echo "Service loaded. Memory: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    // Try a simpler question first
    echo "Testing simple question..." . PHP_EOL;
    $result = $chatbotService->askQuestion('How many bills are there?');
    
    echo "Question processed. Memory: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    echo "Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    if ($result['success']) {
        echo "✅ SUCCESS!" . PHP_EOL;
        echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        echo "Response length: " . strlen($result['response']) . PHP_EOL;
    } else {
        echo "❌ FAILED: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "💥 Exception: " . $e->getMessage() . PHP_EOL;
    echo "Memory at error: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
} catch (Error $e) {
    echo "💥 Fatal Error: " . $e->getMessage() . PHP_EOL;
    echo "Memory at error: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "Final memory: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
echo "Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;