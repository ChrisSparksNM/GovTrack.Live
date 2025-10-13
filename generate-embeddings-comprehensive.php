<?php

echo "=== Comprehensive Embedding Generation ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will generate embeddings for all your congressional data." . PHP_EOL;
echo "The chatbot will then be able to search and analyze:" . PHP_EOL;
echo "- Bills by topic (healthcare, defense, etc.)" . PHP_EOL;
echo "- Member information and activities" . PHP_EOL;
echo "- Legislative actions and history" . PHP_EOL;
echo "" . PHP_EOL;

// Check current state
$billCount = DB::table('bills')->count();
$memberCount = DB::table('members')->count();
$lawCount = DB::table('laws')->count();
$currentEmbeddings = DB::table('embeddings')->count();

echo "Current data:" . PHP_EOL;
echo "- Bills: $billCount" . PHP_EOL;
echo "- Members: $memberCount" . PHP_EOL;
echo "- Laws: $lawCount" . PHP_EOL;
echo "- Existing embeddings: $currentEmbeddings" . PHP_EOL;
echo "" . PHP_EOL;

if ($currentEmbeddings > 0) {
    echo "⚠️  You already have $currentEmbeddings embeddings." . PHP_EOL;
    echo "Do you want to regenerate them? (y/N): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) !== 'y') {
        echo "Cancelled. Your existing embeddings are preserved." . PHP_EOL;
        exit;
    }
    
    echo "Regenerating all embeddings..." . PHP_EOL;
    $forceFlag = '--force';
} else {
    echo "🚀 Starting fresh embedding generation..." . PHP_EOL;
    $forceFlag = '';
}

echo "" . PHP_EOL;
echo "Starting embedding generation process..." . PHP_EOL;
echo "This may take 10-30 minutes depending on your data size." . PHP_EOL;
echo "" . PHP_EOL;

// Generate embeddings using the artisan command
$startTime = time();

echo "Step 1: Generating bill embeddings..." . PHP_EOL;
$output = [];
$returnCode = 0;
exec("php artisan embeddings:generate --type=bills $forceFlag 2>&1", $output, $returnCode);

foreach ($output as $line) {
    echo $line . PHP_EOL;
}

if ($returnCode !== 0) {
    echo "❌ Bill embedding generation failed!" . PHP_EOL;
    exit(1);
}

echo "" . PHP_EOL;
echo "Step 2: Generating member embeddings..." . PHP_EOL;
$output = [];
exec("php artisan embeddings:generate --type=members $forceFlag 2>&1", $output, $returnCode);

foreach ($output as $line) {
    echo $line . PHP_EOL;
}

if ($returnCode !== 0) {
    echo "❌ Member embedding generation failed!" . PHP_EOL;
    exit(1);
}

// Check if we have bill actions to embed
$actionCount = DB::table('bill_actions')->count();
if ($actionCount > 0) {
    echo "" . PHP_EOL;
    echo "Step 3: Generating action embeddings..." . PHP_EOL;
    $output = [];
    exec("php artisan embeddings:generate --type=actions $forceFlag 2>&1", $output, $returnCode);
    
    foreach ($output as $line) {
        echo $line . PHP_EOL;
    }
}

$endTime = time();
$duration = $endTime - $startTime;
$minutes = floor($duration / 60);
$seconds = $duration % 60;

echo "" . PHP_EOL;
echo "🎉 EMBEDDING GENERATION COMPLETE!" . PHP_EOL;
echo "Total time: {$minutes}m {$seconds}s" . PHP_EOL;

// Show final stats
$finalEmbeddings = DB::table('embeddings')->count();
echo "Total embeddings created: $finalEmbeddings" . PHP_EOL;

$embeddingsByType = DB::table('embeddings')
    ->select('entity_type', DB::raw('COUNT(*) as count'))
    ->groupBy('entity_type')
    ->get();

echo "" . PHP_EOL;
echo "Embeddings by type:" . PHP_EOL;
foreach ($embeddingsByType as $stat) {
    echo "- {$stat->entity_type}: {$stat->count}" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "✅ Your chatbot is now trained on all your congressional data!" . PHP_EOL;
echo "Try asking: 'What healthcare bills have been introduced recently?'" . PHP_EOL;
echo "Or: 'Show me bills about climate change'" . PHP_EOL;