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

// Function to run command with live output
function runCommandWithProgress($command, $stepName) {
    echo "🔄 $stepName" . PHP_EOL;
    echo str_repeat("-", 60) . PHP_EOL;
    
    $startTime = time();
    
    // Use popen to get live output
    $handle = popen($command . ' 2>&1', 'r');
    
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            echo $line;
            flush(); // Force output to display immediately
        }
        
        $returnCode = pclose($handle);
        
        $duration = time() - $startTime;
        $minutes = floor($duration / 60);
        $seconds = $duration % 60;
        
        if ($returnCode === 0) {
            echo "✅ $stepName completed in {$minutes}m {$seconds}s" . PHP_EOL;
        } else {
            echo "❌ $stepName failed after {$minutes}m {$seconds}s (exit code: $returnCode)" . PHP_EOL;
            return false;
        }
    } else {
        echo "❌ Failed to start $stepName" . PHP_EOL;
        return false;
    }
    
    echo "" . PHP_EOL;
    return true;
}

// Function to show progress between steps
function showProgress($step, $total, $description) {
    $percentage = round(($step / $total) * 100);
    $progressBar = str_repeat("█", floor($percentage / 5)) . str_repeat("░", 20 - floor($percentage / 5));
    echo "📊 Overall Progress: [$progressBar] $percentage% - $description" . PHP_EOL;
    echo "" . PHP_EOL;
}

$totalSteps = 2; // Bills + Members (+ Actions if available)
$actionCount = DB::table('bill_actions')->count();
if ($actionCount > 0) {
    $totalSteps = 3;
}

$overallStartTime = time();

// Step 1: Generate bill embeddings
showProgress(0, $totalSteps, "Starting bill embeddings...");
if (!runCommandWithProgress("php artisan embeddings:generate --type=bills $forceFlag", "Step 1: Generating bill embeddings")) {
    exit(1);
}

// Show intermediate progress
$billEmbeddings = DB::table('embeddings')->where('entity_type', 'bill')->count();
echo "✅ Created $billEmbeddings bill embeddings" . PHP_EOL;
showProgress(1, $totalSteps, "Bills complete, starting members...");

// Step 2: Generate member embeddings
if (!runCommandWithProgress("php artisan embeddings:generate --type=members $forceFlag", "Step 2: Generating member embeddings")) {
    exit(1);
}

// Show intermediate progress
$memberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();
echo "✅ Created $memberEmbeddings member embeddings" . PHP_EOL;

// Step 3: Generate action embeddings (if available)
if ($actionCount > 0) {
    showProgress(2, $totalSteps, "Members complete, starting actions...");
    
    if (!runCommandWithProgress("php artisan embeddings:generate --type=actions $forceFlag", "Step 3: Generating action embeddings")) {
        echo "⚠️  Action embeddings failed, but continuing..." . PHP_EOL;
    } else {
        $actionEmbeddings = DB::table('embeddings')->where('entity_type', 'bill_action')->count();
        echo "✅ Created $actionEmbeddings action embeddings" . PHP_EOL;
    }
}

$overallEndTime = time();
$totalDuration = $overallEndTime - $overallStartTime;
$totalMinutes = floor($totalDuration / 60);
$totalSeconds = $totalDuration % 60;

echo str_repeat("=", 60) . PHP_EOL;
echo "🎉 EMBEDDING GENERATION COMPLETE!" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL;
echo "⏱️  Total time: {$totalMinutes}m {$totalSeconds}s" . PHP_EOL;

// Show final stats
$finalEmbeddings = DB::table('embeddings')->count();
echo "📊 Total embeddings created: $finalEmbeddings" . PHP_EOL;

$embeddingsByType = DB::table('embeddings')
    ->select('entity_type', DB::raw('COUNT(*) as count'))
    ->groupBy('entity_type')
    ->get();

echo "" . PHP_EOL;
echo "📈 Embeddings by type:" . PHP_EOL;
foreach ($embeddingsByType as $stat) {
    echo "   - {$stat->entity_type}: {$stat->count}" . PHP_EOL;
}

// Estimate storage size
$avgEmbeddingSize = 1536 * 4; // 1536 dimensions * 4 bytes per float
$estimatedSize = ($finalEmbeddings * $avgEmbeddingSize) / (1024 * 1024); // MB
echo "" . PHP_EOL;
echo "💾 Estimated storage size: " . round($estimatedSize, 2) . " MB" . PHP_EOL;

echo "" . PHP_EOL;
echo "✅ Your chatbot is now trained on all your congressional data!" . PHP_EOL;
echo "" . PHP_EOL;
echo "🧪 Try these test questions:" . PHP_EOL;
echo "   • 'What healthcare bills have been introduced recently?'" . PHP_EOL;
echo "   • 'Show me bills about climate change'" . PHP_EOL;
echo "   • 'Which members are most active on defense issues?'" . PHP_EOL;
echo "   • 'Find bills related to immigration'" . PHP_EOL;