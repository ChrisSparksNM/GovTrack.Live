<?php

echo "=== Simple Resume Embedding Generation ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check current state
$totalBills = DB::table('bills')->count();
$totalMembers = DB::table('members')->count();

// Get IDs that already have embeddings
$embeddedBillIds = DB::table('embeddings')
    ->where('entity_type', 'bill')
    ->pluck('entity_id')
    ->toArray();

$embeddedMemberIds = DB::table('embeddings')
    ->where('entity_type', 'member')
    ->pluck('entity_id')
    ->toArray();

$billsWithEmbeddings = count($embeddedBillIds);
$membersWithEmbeddings = count($embeddedMemberIds);

echo "📊 Current Status:" . PHP_EOL;
echo "- Total bills: $totalBills" . PHP_EOL;
echo "- Bills with embeddings: $billsWithEmbeddings" . PHP_EOL;
echo "- Bills missing: " . ($totalBills - $billsWithEmbeddings) . PHP_EOL;
echo "" . PHP_EOL;
echo "- Total members: $totalMembers" . PHP_EOL;
echo "- Members with embeddings: $membersWithEmbeddings" . PHP_EOL;
echo "- Members missing: " . ($totalMembers - $membersWithEmbeddings) . PHP_EOL;
echo "" . PHP_EOL;

// Check if we need to do anything
$billsToProcess = $totalBills - $billsWithEmbeddings;
$membersToProcess = $totalMembers - $membersWithEmbeddings;

if ($billsToProcess == 0 && $membersToProcess == 0) {
    echo "🎉 All embeddings are complete!" . PHP_EOL;
    
    // Test the chatbot
    echo "Testing chatbot..." . PHP_EOL;
    try {
        $chatbotService = app('App\Services\CongressChatbotService');
        $result = $chatbotService->askQuestion('What healthcare bills exist?');
        
        if ($result['success'] && (strpos($result['response'], 'HR ') !== false || strpos($result['response'], 'S ') !== false)) {
            echo "✅ Your chatbot is working perfectly!" . PHP_EOL;
            echo "Try: 'What healthcare bills have been introduced recently?'" . PHP_EOL;
        } else {
            echo "⚠️  Embeddings complete but chatbot response seems generic." . PHP_EOL;
            echo "This might be a search parameter issue, not an embedding issue." . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
    }
    exit(0);
}

echo "🔄 Need to process:" . PHP_EOL;
echo "- $billsToProcess bills" . PHP_EOL;
echo "- $membersToProcess members" . PHP_EOL;
echo "" . PHP_EOL;

$estimatedMinutes = ceil(($billsToProcess + $membersToProcess) / 5);
echo "⏱️  Estimated time: ~$estimatedMinutes minutes" . PHP_EOL;
echo "" . PHP_EOL;

echo "Continue? (y/N): ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'y') {
    echo "Cancelled." . PHP_EOL;
    exit(0);
}

// Function to process missing items
function processMissingItems($type, $totalCount, $existingIds, $tableName) {
    $missingCount = $totalCount - count($existingIds);
    
    if ($missingCount <= 0) {
        echo "✅ All $type embeddings already complete!" . PHP_EOL;
        return true;
    }
    
    echo "" . PHP_EOL;
    echo "🔄 Processing $missingCount missing $type embeddings..." . PHP_EOL;
    
    // Get the embedding service
    $embeddingService = app('App\Services\EmbeddingService');
    $documentService = app('App\Services\DocumentEmbeddingService');
    
    // Get items that don't have embeddings
    $query = DB::table($tableName);
    if (!empty($existingIds)) {
        $query->whereNotIn('id', $existingIds);
    }
    
    $missingItems = $query->limit(min($missingCount, 100))->get(); // Process in batches of 100
    
    $processed = 0;
    $successful = 0;
    $failed = 0;
    
    foreach ($missingItems as $item) {
        $processed++;
        
        echo "\r🔄 Processing $type $processed/$missingCount - ID: {$item->id}";
        
        try {
            if ($type === 'bill') {
                // Build bill content
                $content = "Bill: {$item->type} {$item->number}\n";
                $content .= "Title: {$item->title}\n";
                if ($item->summary) {
                    $content .= "Summary: {$item->summary}\n";
                }
                $content .= "Introduced: {$item->introduced_date}\n";
                $content .= "Congress: {$item->congress}\n";
                if ($item->policy_area) {
                    $content .= "Policy Area: {$item->policy_area}\n";
                }
                
                $metadata = [
                    'congress' => $item->congress,
                    'type' => $item->type,
                    'number' => $item->number,
                    'policy_area' => $item->policy_area,
                    'introduced_date' => $item->introduced_date
                ];
                
            } else { // member
                $content = "Member: {$item->first_name} {$item->last_name}\n";
                $content .= "Party: {$item->party_abbreviation}\n";
                $content .= "State: {$item->state}\n";
                $content .= "Chamber: {$item->chamber}\n";
                if ($item->district) {
                    $content .= "District: {$item->district}\n";
                }
                
                $metadata = [
                    'party_abbreviation' => $item->party_abbreviation,
                    'state' => $item->state,
                    'chamber' => $item->chamber,
                    'district' => $item->district
                ];
            }
            
            // Generate embedding
            $embedding = $embeddingService->generateEmbedding($content);
            
            if ($embedding) {
                $success = $embeddingService->storeEmbedding(
                    $type,
                    $item->id,
                    $embedding,
                    $content,
                    $metadata
                );
                
                if ($success) {
                    $successful++;
                } else {
                    $failed++;
                    echo "\n❌ Failed to store embedding for $type {$item->id}\n";
                }
            } else {
                $failed++;
                echo "\n❌ Failed to generate embedding for $type {$item->id}\n";
            }
            
            // Progress update every 25 items
            if ($processed % 25 === 0) {
                echo "\n📊 Progress: $processed/$missingCount ($successful successful, $failed failed)\n";
            }
            
        } catch (Exception $e) {
            $failed++;
            echo "\n❌ Error processing $type {$item->id}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ $type processing complete: $processed processed, $successful successful, $failed failed\n";
    return $successful > 0;
}

$overallStart = time();

// Process bills if needed
if ($billsToProcess > 0) {
    echo "📊 Step 1: Processing missing bill embeddings..." . PHP_EOL;
    processMissingItems('bill', $totalBills, $embeddedBillIds, 'bills');
}

// Process members if needed  
if ($membersToProcess > 0) {
    echo "📊 Step 2: Processing missing member embeddings..." . PHP_EOL;
    processMissingItems('member', $totalMembers, $embeddedMemberIds, 'members');
}

$totalDuration = time() - $overallStart;

echo "" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL;
echo "🎉 RESUME COMPLETE!" . PHP_EOL;
echo str_repeat("=", 60) . PHP_EOL;
echo "⏱️  Total time: " . gmdate("H:i:s", $totalDuration) . PHP_EOL;

// Final check
$finalBillEmbeddings = DB::table('embeddings')->where('entity_type', 'bill')->count();
$finalMemberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();

echo "" . PHP_EOL;
echo "📊 Final Status:" . PHP_EOL;
echo "- Bill embeddings: $finalBillEmbeddings / $totalBills" . PHP_EOL;
echo "- Member embeddings: $finalMemberEmbeddings / $totalMembers" . PHP_EOL;

// Test the chatbot
echo "" . PHP_EOL;
echo "🧪 Testing your chatbot..." . PHP_EOL;

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    $result = $chatbotService->askQuestion('What healthcare bills have been introduced recently?');
    
    if ($result['success']) {
        echo "✅ Chatbot working!" . PHP_EOL;
        echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        
        if (preg_match('/\b(HR|S)\s+\d+/', $result['response'])) {
            echo "✅ Found specific bill references!" . PHP_EOL;
            echo "" . PHP_EOL;
            echo "🎉 YOUR RAG CHATBOT IS WORKING!" . PHP_EOL;
        } else {
            echo "⚠️  Response seems generic but embeddings are there." . PHP_EOL;
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

echo "" . PHP_EOL;
echo "🚀 Try asking your chatbot:" . PHP_EOL;
echo "   • 'What healthcare bills have been introduced recently?'" . PHP_EOL;
echo "   • 'Show me climate change legislation'" . PHP_EOL;
echo "   • 'Which members sponsor the most bills?'" . PHP_EOL;