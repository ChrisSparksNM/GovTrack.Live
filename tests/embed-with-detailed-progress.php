<?php

echo "=== Enhanced Embedding Generation with Detailed Progress ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check current state
$billCount = DB::table('bills')->count();
$memberCount = DB::table('members')->count();
$currentEmbeddings = DB::table('embeddings')->count();

echo "Current data:" . PHP_EOL;
echo "- Bills: $billCount" . PHP_EOL;
echo "- Members: $memberCount" . PHP_EOL;
echo "- Existing embeddings: $currentEmbeddings" . PHP_EOL;
echo "" . PHP_EOL;

if ($currentEmbeddings > 0) {
    echo "⚠️  You already have $currentEmbeddings embeddings." . PHP_EOL;
    echo "Options:" . PHP_EOL;
    echo "1. Skip if you have enough (recommended if > 5000)" . PHP_EOL;
    echo "2. Regenerate all (will take 30-60 minutes for $billCount bills)" . PHP_EOL;
    echo "3. Generate only missing ones" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Choose (1/2/3): ";
    
    $handle = fopen("php://stdin", "r");
    $choice = trim(fgets($handle));
    fclose($handle);
    
    if ($choice === '1') {
        echo "✅ Keeping existing embeddings. Testing chatbot..." . PHP_EOL;
        
        // Test if current embeddings work
        try {
            $chatbotService = app('App\Services\CongressChatbotService');
            $result = $chatbotService->askQuestion('What healthcare bills exist?');
            
            if ($result['success'] && strpos($result['response'], 'HR ') !== false) {
                echo "✅ Your chatbot is already working with existing embeddings!" . PHP_EOL;
                echo "Try asking: 'What healthcare bills have been introduced recently?'" . PHP_EOL;
                exit(0);
            } else {
                echo "⚠️  Embeddings exist but chatbot isn't finding specific data." . PHP_EOL;
                echo "Continuing with regeneration..." . PHP_EOL;
            }
        } catch (Exception $e) {
            echo "❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
            echo "Continuing with regeneration..." . PHP_EOL;
        }
    }
    
    $forceFlag = ($choice === '2') ? '--force' : '';
} else {
    echo "🚀 Starting fresh embedding generation..." . PHP_EOL;
    $forceFlag = '';
}

echo "" . PHP_EOL;

// Function to run command with real-time progress monitoring
function runWithDetailedProgress($command, $stepName, $expectedCount) {
    echo "🔄 $stepName" . PHP_EOL;
    echo "Expected items: $expectedCount" . PHP_EOL;
    echo str_repeat("-", 60) . PHP_EOL;
    
    $startTime = time();
    $lastProgressTime = $startTime;
    $lastCount = 0;
    
    // Start the process
    $process = popen($command . ' 2>&1', 'r');
    
    if (!$process) {
        echo "❌ Failed to start process" . PHP_EOL;
        return false;
    }
    
    // Monitor progress
    while (!feof($process)) {
        $line = fgets($process);
        if ($line !== false) {
            echo $line;
            flush();
            
            // Check for progress indicators every 30 seconds
            $currentTime = time();
            if ($currentTime - $lastProgressTime >= 30) {
                $currentCount = getCurrentEmbeddingCount();
                $processed = $currentCount - $lastCount;
                $rate = $processed > 0 ? $processed / 30 : 0;
                $remaining = max(0, $expectedCount - $currentCount);
                $eta = $rate > 0 ? round($remaining / $rate / 60, 1) : 'unknown';
                
                echo "" . PHP_EOL;
                echo "📊 Progress Update:" . PHP_EOL;
                echo "   Current embeddings: $currentCount" . PHP_EOL;
                echo "   Processing rate: " . round($rate, 1) . " items/sec" . PHP_EOL;
                echo "   Estimated time remaining: {$eta} minutes" . PHP_EOL;
                echo "   " . str_repeat("█", min(50, round($currentCount / $expectedCount * 50))) . 
                     str_repeat("░", max(0, 50 - round($currentCount / $expectedCount * 50))) . 
                     " " . round($currentCount / $expectedCount * 100, 1) . "%" . PHP_EOL;
                echo "" . PHP_EOL;
                
                $lastProgressTime = $currentTime;
                $lastCount = $currentCount;
            }
        }
    }
    
    $returnCode = pclose($process);
    $duration = time() - $startTime;
    
    if ($returnCode === 0) {
        echo "✅ $stepName completed in " . gmdate("H:i:s", $duration) . PHP_EOL;
        return true;
    } else {
        echo "❌ $stepName failed after " . gmdate("H:i:s", $duration) . PHP_EOL;
        return false;
    }
}

function getCurrentEmbeddingCount() {
    try {
        return DB::table('embeddings')->count();
    } catch (Exception $e) {
        return 0;
    }
}

// Estimate processing time
$estimatedMinutes = ceil(($billCount + $memberCount) / 5); // ~5 items per minute
echo "⏱️  Estimated total time: ~$estimatedMinutes minutes" . PHP_EOL;
echo "💡 Tip: This process can run in the background. You can safely Ctrl+C and resume later." . PHP_EOL;
echo "" . PHP_EOL;

$overallStart = time();

// Step 1: Bills (this is the big one)
echo "📊 Overall Progress: [░░░░░░░░░░░░░░░░░░░░] 0% - Starting bills..." . PHP_EOL;
if (!runWithDetailedProgress("php artisan embeddings:generate --type=bills --batch-size=25 $forceFlag", "Generating bill embeddings", $billCount)) {
    echo "❌ Bill embedding generation failed. Check your API keys and try again." . PHP_EOL;
    exit(1);
}

$billsComplete = time();
$billDuration = $billsComplete - $overallStart;
echo "✅ Bills completed in " . gmdate("H:i:s", $billDuration) . PHP_EOL;
echo "" . PHP_EOL;

// Step 2: Members (much faster)
echo "📊 Overall Progress: [██████████░░░░░░░░░░] 50% - Starting members..." . PHP_EOL;
if (!runWithDetailedProgress("php artisan embeddings:generate --type=members --batch-size=50 $forceFlag", "Generating member embeddings", $memberCount)) {
    echo "⚠️  Member embedding generation failed, but continuing..." . PHP_EOL;
}

$totalDuration = time() - $overallStart;

echo "" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL;
echo "🎉 EMBEDDING GENERATION COMPLETE!" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL;
echo "⏱️  Total time: " . gmdate("H:i:s", $totalDuration) . PHP_EOL;

// Final stats
$finalEmbeddings = DB::table('embeddings')->count();
echo "📊 Total embeddings: $finalEmbeddings" . PHP_EOL;

$embeddingsByType = DB::table('embeddings')
    ->select('entity_type', DB::raw('COUNT(*) as count'))
    ->groupBy('entity_type')
    ->get();

echo "" . PHP_EOL;
echo "📈 Breakdown:" . PHP_EOL;
foreach ($embeddingsByType as $stat) {
    echo "   - {$stat->entity_type}: {$stat->count}" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "🧪 Testing your RAG chatbot..." . PHP_EOL;

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    $result = $chatbotService->askQuestion('What healthcare bills have been introduced recently?');
    
    if ($result['success']) {
        echo "✅ Chatbot test successful!" . PHP_EOL;
        echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        
        if (strpos($result['response'], 'HR ') !== false || strpos($result['response'], 'S ') !== false) {
            echo "✅ Found specific bill references in response!" . PHP_EOL;
        } else {
            echo "⚠️  Response seems generic. May need to adjust search parameters." . PHP_EOL;
        }
    } else {
        echo "❌ Chatbot test failed: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
    }
} catch (Exception $e) {
    echo "❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "🚀 Your RAG chatbot is ready!" . PHP_EOL;
echo "Try these questions:" . PHP_EOL;
echo "   • 'What healthcare bills have been introduced recently?'" . PHP_EOL;
echo "   • 'Show me climate change legislation'" . PHP_EOL;
echo "   • 'Which members sponsor the most bills?'" . PHP_EOL;