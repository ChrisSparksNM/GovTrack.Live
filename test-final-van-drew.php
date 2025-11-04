<?php

echo "=== Final Test: Jefferson Van Drew's Most Recent Bills ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "Testing the exact user question..." . PHP_EOL;
    $result = $chatbotService->askQuestion("Tell me about Jefferson Van Drew's most recent bills");
    
    if ($result['success']) {
        echo "✅ SUCCESS!" . PHP_EOL;
        echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        echo "Data sources: " . implode(', ', $result['data_sources'] ?? []) . PHP_EOL;
        echo "" . PHP_EOL;
        echo "RESPONSE:" . PHP_EOL;
        echo "========================================" . PHP_EOL;
        echo $result['response'] . PHP_EOL;
        echo "========================================" . PHP_EOL;
        
        // Check for key indicators of success
        $response = $result['response'];
        $hasVanDrewName = preg_match('/Jefferson Van Drew|Van Drew/i', $response);
        $hasBillNumbers = preg_match('/HR\s*\d+|S\s*\d+/i', $response);
        $hasRecentActivity = preg_match('/recent|2025|October|September/i', $response);
        $hasSpecificBills = preg_match('/\d+\s+bills?/i', $response);
        
        echo "" . PHP_EOL;
        echo "SUCCESS INDICATORS:" . PHP_EOL;
        echo "✅ Mentions Van Drew: " . ($hasVanDrewName ? "YES" : "NO") . PHP_EOL;
        echo "✅ Shows bill numbers: " . ($hasBillNumbers ? "YES" : "NO") . PHP_EOL;
        echo "✅ Mentions recent activity: " . ($hasRecentActivity ? "YES" : "NO") . PHP_EOL;
        echo "✅ Shows specific bill counts: " . ($hasSpecificBills ? "YES" : "NO") . PHP_EOL;
        
        if ($hasVanDrewName && $hasBillNumbers && $hasRecentActivity && $hasSpecificBills) {
            echo "" . PHP_EOL;
            echo "🎉 PERFECT! All success indicators met!" . PHP_EOL;
        } else {
            echo "" . PHP_EOL;
            echo "⚠️  Some indicators missing, but much better than before!" . PHP_EOL;
        }
        
    } else {
        echo "❌ FAILED: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Test Complete ===" . PHP_EOL;