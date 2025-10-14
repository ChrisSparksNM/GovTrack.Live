<?php

echo "=== Optimize Bills Memory Usage ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "1. Analyzing bill data size..." . PHP_EOL;
    
    $totalBills = DB::table('bills')->count();
    echo "Total bills: $totalBills" . PHP_EOL;
    
    // Check embeddings for bills
    $billEmbeddings = DB::table('embeddings')
        ->where('entity_type', 'bill')
        ->count();
    echo "Bill embeddings: $billEmbeddings" . PHP_EOL;
    
    // Sample bill size
    $sampleBill = DB::table('bills')->first();
    $billSize = strlen(serialize($sampleBill));
    echo "Average bill record size: ~" . round($billSize / 1024, 2) . " KB" . PHP_EOL;
    
    $estimatedMemory = ($totalBills * $billSize) / (1024 * 1024);
    echo "Estimated memory for all bills: ~" . round($estimatedMemory, 2) . " MB" . PHP_EOL;
    
    echo "" . PHP_EOL;
    echo "2. Testing chunked bill processing..." . PHP_EOL;
    
    // Test processing bills in chunks
    $chunkSize = 100;
    $chunks = ceil($totalBills / $chunkSize);
    echo "Processing $totalBills bills in $chunks chunks of $chunkSize" . PHP_EOL;
    
    $startMemory = memory_get_usage(true);
    
    for ($i = 0; $i < min(3, $chunks); $i++) { // Test first 3 chunks
        $offset = $i * $chunkSize;
        
        $chunkBills = DB::table('bills')
            ->offset($offset)
            ->limit($chunkSize)
            ->get(['id', 'title', 'type', 'number']);
        
        $currentMemory = memory_get_usage(true);
        $memoryUsed = ($currentMemory - $startMemory) / (1024 * 1024);
        
        echo "  Chunk " . ($i + 1) . ": " . count($chunkBills) . " bills, Memory: +" . round($memoryUsed, 2) . " MB" . PHP_EOL;
        
        // Clear chunk from memory
        unset($chunkBills);
    }
    
    echo "" . PHP_EOL;
    echo "3. Memory optimization recommendations..." . PHP_EOL;
    
    if ($estimatedMemory > 400) {
        echo "⚠️  Bills require significant memory (~{$estimatedMemory}MB)" . PHP_EOL;
        echo "Recommendations:" . PHP_EOL;
        echo "  1. Increase server memory limit to 1024M (easiest)" . PHP_EOL;
        echo "  2. Implement chunked processing for bill queries" . PHP_EOL;
        echo "  3. Add bill-specific memory optimizations" . PHP_EOL;
    } else {
        echo "✅ Bills should fit in memory with current optimization" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}