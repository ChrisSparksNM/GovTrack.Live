<?php

echo "=== Check Database for Embedding Generation ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "1. Current database content:" . PHP_EOL;
    
    // Check bills
    $billCount = DB::table('bills')->count();
    echo "Bills: $billCount" . PHP_EOL;
    
    if ($billCount > 0) {
        $sampleBill = DB::table('bills')->first();
        echo "Sample bill fields: " . implode(', ', array_keys((array)$sampleBill)) . PHP_EOL;
        
        // Check for healthcare bills
        $healthcareBills = DB::table('bills')
            ->where('title', 'like', '%health%')
            ->orWhere('title', 'like', '%medical%')
            ->orWhere('title', 'like', '%medicare%')
            ->orWhere('title', 'like', '%medicaid%')
            ->count();
        echo "Healthcare-related bills: $healthcareBills" . PHP_EOL;
    }
    
    // Check laws
    $lawCount = DB::table('laws')->count();
    echo "Laws: $lawCount" . PHP_EOL;
    
    // Check members
    $memberCount = DB::table('members')->count();
    echo "Members: $memberCount" . PHP_EOL;
    
    // Check bill actions
    $actionCount = DB::table('bill_actions')->count();
    echo "Bill actions: $actionCount" . PHP_EOL;
    
    echo "" . PHP_EOL;
    echo "2. Current embeddings:" . PHP_EOL;
    
    $embeddingStats = DB::table('embeddings')
        ->select('entity_type', DB::raw('COUNT(*) as count'))
        ->groupBy('entity_type')
        ->get();
    
    if ($embeddingStats->count() > 0) {
        foreach ($embeddingStats as $stat) {
            echo "{$stat->entity_type}: {$stat->count} embeddings" . PHP_EOL;
        }
    } else {
        echo "❌ No embeddings found! This is why the chatbot can't find specific data." . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    echo "3. Recommendations:" . PHP_EOL;
    
    if ($embeddingStats->count() == 0) {
        echo "🚀 You need to generate embeddings for your data!" . PHP_EOL;
        echo "Run these commands:" . PHP_EOL;
        echo "  php artisan embeddings:generate --type=bills" . PHP_EOL;
        echo "  php artisan embeddings:generate --type=members" . PHP_EOL;
        
        if ($actionCount > 0) {
            echo "  php artisan embeddings:generate --type=actions" . PHP_EOL;
        }
        
        echo "" . PHP_EOL;
        echo "Or generate all at once:" . PHP_EOL;
        echo "  php artisan embeddings:generate --type=all" . PHP_EOL;
    } else {
        echo "✅ Some embeddings exist, but you may want to regenerate:" . PHP_EOL;
        echo "  php artisan embeddings:generate --type=all --force" . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    echo "4. Estimated generation time:" . PHP_EOL;
    $totalItems = $billCount + $lawCount + $memberCount + ($actionCount > 0 ? min($actionCount, 1000) : 0);
    $estimatedMinutes = ceil($totalItems / 10); // Roughly 10 items per minute
    echo "Total items to embed: ~$totalItems" . PHP_EOL;
    echo "Estimated time: ~$estimatedMinutes minutes" . PHP_EOL;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}