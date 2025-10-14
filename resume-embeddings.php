<?php

echo "=== Resume Embedding Generation ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check current state
$totalBills = DB::table('bills')->count();
$totalMembers = DB::table('members')->count();
$totalEmbeddings = DB::table('embeddings')->count();

// Check what's already embedded
$billEmbeddings = DB::table('embeddings')->where('entity_type', 'bill')->count();
$memberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();

echo "📊 Current Status:" . PHP_EOL;
echo "- Total bills: $totalBills" . PHP_EOL;
echo "- Bills with embeddings: $billEmbeddings" . PHP_EOL;
echo "- Bills missing embeddings: " . ($totalBills - $billEmbeddings) . PHP_EOL;
echo "" . PHP_EOL;
echo "- Total members: $totalMembers" . PHP_EOL;
echo "- Members with embeddings: $memberEmbeddings" . PHP_EOL;
echo "- Members missing embeddings: " . ($totalMembers - $memberEmbeddings) . PHP_EOL;
echo "" . PHP_EOL;
echo "- Total embeddings: $totalEmbeddings" . PHP_EOL;

// Calculate what needs to be done
$billsToProcess = $totalBills - $billEmbeddings;
$membersToProcess = $totalMembers - $memberEmbeddings;
$totalToProcess = $billsToProcess + $membersToProcess;

if ($totalToProcess == 0) {
    echo "🎉 All embeddings are complete!" . PHP_EOL;
    echo "Testing your chatbot..." . PHP_EOL;
    
    try {
        $chatbotService = app('App\Services\CongressChatbotService');
        $result = $chatbotService->askQuestion('What healthcare bills exist?');
        
        if ($result['success'] && (strpos($result['response'], 'HR ') !== false || strpos($result['response'], 'S ') !== false)) {
            echo "✅ Your chatbot is working perfectly!" . PHP_EOL;
            exit(0);
        } else {
            echo "⚠️  Embeddings complete but chatbot needs tuning." . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
    }
    exit(0);
}

echo "🔄 Need to process $totalToProcess items:" . PHP_EOL;
echo "- $billsToProcess bills" . PHP_EOL;
echo "- $membersToProcess members" . PHP_EOL;
echo "" . PHP_EOL;

$estimatedMinutes = ceil($totalToProcess / 5); // ~5 items per minute
echo "⏱️  Estimated time: ~$estimatedMinutes minutes" . PHP_EOL;
echo "" . PHP_EOL;

echo "Continue with missing embeddings? (y/N): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'y') {
    echo "Cancelled." . PHP_EOL;
    exit(0);
}

// Function to process missing embeddings with progress
function processWithResume($type, $expectedCount, $currentCount) {
    if ($expectedCount <= $currentCount) {
        echo "✅ All $type embeddings already complete!" . PHP_EOL;
        return true;
    }
    
    $toProcess = $expectedCount - $currentCount;
    echo "" . PHP_EOL;
    echo "🔄 Processing $toProcess missing $type embeddings..." . PHP_EOL;
    echo str_repeat("-", 50) . PHP_EOL;
    
    $startTime = time();
    $lastCheck = $startTime;
    $lastCount = $currentCount;
    
    // Use a custom command that only processes missing items
    $command = "php artisan embeddings:generate --type=$type --batch-size=25 --skip-existing 2>&1";
    $process = popen($command, 'r');
    
    if (!$process) {
        echo "❌ Failed to start $type embedding process" . PHP_EOL;
        return false;
    }
    
    while (!feof($process)) {
        $line = fgets($process);
        if ($line !== false) {
            echo $line;
            flush();
            
            // Check progress every 60 seconds
            $currentTime = time();
            if ($currentTime - $lastCheck >= 60) {
                $newCount = getCurrentEmbeddingCountByType($type);
                $processed = $newCount - $lastCount;
                $rate = $processed > 0 ? $processed / 60 : 0;
                $remaining = max(0, $expectedCount - $newCount);
                $eta = $rate > 0 ? round($remaining / $rate / 60, 1) : 'calculating...';
                
                $progress = $expectedCount > 0 ? round(($newCount / $expectedCount) * 100, 1) : 0;
                
                echo "" . PHP_EOL;
                echo "📊 Progress Update:" . PHP_EOL;
                echo "   $type embeddings: $newCount / $expectedCount ($progress%)" . PHP_EOL;
                echo "   Rate: " . round($rate * 60, 1) . " items/min" . PHP_EOL;
                echo "   ETA: $eta minutes" . PHP_EOL;
                echo "   " . str_repeat("█", min(30, round($progress / 100 * 30))) . 
                     str_repeat("░", max(0, 30 - round($progress / 100 * 30))) . PHP_EOL;
                echo "" . PHP_EOL;
                
                $lastCheck = $currentTime;
                $lastCount = $newCount;
            }
        }
    }
    
    $returnCode = pclose($process);
    $duration = time() - $startTime;
    
    if ($returnCode === 0) {
        echo "✅ $type embeddings completed in " . gmdate("H:i:s", $duration) . PHP_EOL;
        return true;
    } else {
        echo "⚠️  $type embeddings had issues but may have partially completed" . PHP_EOL;
        return false;
    }
}

function getCurrentEmbeddingCountByType($type) {
    try {
        return DB::table('embeddings')->where('entity_type', $type)->count();
    } catch (Exception $e) {
        return 0;
    }
}

$overallStart = time();

// Process bills if needed
if ($billsToProcess > 0) {
    echo "📊 Step 1: Resuming bill embeddings..." . PHP_EOL;
    processWithResume('bill', $totalBills, $billEmbeddings);
} else {
    echo "✅ All bill embeddings already complete!" . PHP_EOL;
}

// Process members if needed
if ($membersToProcess > 0) {
    echo "📊 Step 2: Resuming member embeddings..." . PHP_EOL;
    processWithResume('member', $totalMembers, $memberEmbeddings);
} else {
    echo "✅ All member embeddings already complete!" . PHP_EOL;
}

$totalDuration = time() - $overallStart;

echo "" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL;
echo "🎉 RESUME COMPLETE!" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL;
echo "⏱️  Total time: " . gmdate("H:i:s", $totalDuration) . PHP_EOL;

// Final verification
$finalBillEmbeddings = DB::table('embeddings')->where('entity_type', 'bill')->count();
$finalMemberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();
$finalTotal = DB::table('embeddings')->count();

echo "" . PHP_EOL;
echo "📊 Final Status:" . PHP_EOL;
echo "- Bill embeddings: $finalBillEmbeddings / $totalBills (" . round($finalBillEmbeddings / $totalBills * 100, 1) . "%)" . PHP_EOL;
echo "- Member embeddings: $finalMemberEmbeddings / $totalMembers (" . round($finalMemberEmbeddings / $totalMembers * 100, 1) . "%)" . PHP_EOL;
echo "- Total embeddings: $finalTotal" . PHP_EOL;

if ($finalBillEmbeddings >= $totalBills * 0.8) { // At least 80% complete
    echo "" . PHP_EOL;
    echo "🧪 Testing your chatbot..." . PHP_EOL;
    
    try {
        $chatbotService = app('App\Services\CongressChatbotService');
        $result = $chatbotService->askQuestion('What healthcare bills have been introduced recently?');
        
        if ($result['success']) {
            echo "✅ Chatbot test successful!" . PHP_EOL;
            echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
            
            if (preg_match('/\b(HR|S)\s+\d+/', $result['response'])) {
                echo "✅ Found specific bill references!" . PHP_EOL;
                echo "" . PHP_EOL;
                echo "🎉 YOUR RAG CHATBOT IS WORKING!" . PHP_EOL;
            } else {
                echo "⚠️  Response seems generic, but embeddings are there." . PHP_EOL;
            }
            
            echo "" . PHP_EOL;
            echo "Sample response:" . PHP_EOL;
            echo substr($result['response'], 0, 300) . "..." . PHP_EOL;
        } else {
            echo "❌ Chatbot test failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
    }
} else {
    echo "" . PHP_EOL;
    echo "⚠️  Less than 80% of bills have embeddings. Consider running again to complete." . PHP_EOL;
}

echo "" . PHP_EOL;
echo "🚀 Try these questions in your web interface:" . PHP_EOL;
echo "   • 'What healthcare bills have been introduced recently?'" . PHP_EOL;
echo "   • 'Show me climate change legislation'" . PHP_EOL;
echo "   • 'Which members sponsor the most bills?'" . PHP_EOL;