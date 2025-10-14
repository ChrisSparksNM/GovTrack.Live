<?php

echo "=== Regenerate Recent Embeddings Only ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will regenerate embeddings ONLY for recent bills (2024+) and current members." . PHP_EOL;
echo "This is much faster than regenerating everything!" . PHP_EOL;
echo "" . PHP_EOL;

// Check current status
echo "1. Checking current data..." . PHP_EOL;

$recentBills = DB::table('bills')->where('introduced_date', '>=', '2024-01-01')->count();
$currentMembers = DB::table('members')->where('current_member', true)->count();
$recentBillEmbeddings = DB::table('embeddings')
    ->join('bills', function($join) {
        $join->on('embeddings.entity_id', '=', 'bills.id')
             ->where('embeddings.entity_type', '=', 'bill');
    })
    ->where('bills.introduced_date', '>=', '2024-01-01')
    ->count();

echo "   Recent bills (2024+): $recentBills" . PHP_EOL;
echo "   Current members: $currentMembers" . PHP_EOL;
echo "   Existing recent bill embeddings: $recentBillEmbeddings" . PHP_EOL;
echo "   Total to process: " . ($recentBills + $currentMembers) . PHP_EOL;
echo "   ⏱️  Estimated time: " . ceil(($recentBills + $currentMembers) / 100 * 2) . " minutes" . PHP_EOL;

echo "" . PHP_EOL;

// Enhanced embedding generation function
function generateEnhancedEmbeddingContent($entity, $entityType) {
    $content = '';
    
    switch ($entityType) {
        case 'bill':
            // Start with strong date emphasis for recent bills
            $year = date('Y', strtotime($entity->introduced_date));
            $month = date('F', strtotime($entity->introduced_date));
            
            $content .= "CURRENT CONGRESS $year BILL: ";
            $content .= "{$entity->type} {$entity->number} - {$entity->title}";
            
            // Add prominent date information
            $content .= " | INTRODUCED: $month $year ({$entity->introduced_date})";
            $content .= " | RECENT LEGISLATIVE ACTIVITY";
            
            // Add sponsor information with emphasis
            if (!empty($entity->sponsor_name)) {
                $content .= " | ACTIVE SPONSOR: {$entity->sponsor_name}";
                if (!empty($entity->sponsor_party) && !empty($entity->sponsor_state)) {
                    $content .= " ({$entity->sponsor_party}-{$entity->sponsor_state})";
                }
            }
            
            // Add subject areas
            if (!empty($entity->subjects)) {
                $subjects = is_string($entity->subjects) ? $entity->subjects : implode(', ', $entity->subjects);
                $content .= " | CURRENT TOPICS: {$subjects}";
            }
            
            // Add summary
            if (!empty($entity->summary)) {
                $summary = substr($entity->summary, 0, 300);
                $content .= " | SUMMARY: {$summary}";
            }
            
            // Add temporal keywords for better matching
            $content .= " | KEYWORDS: recent bill, current congress, $year legislation, active sponsor";
            
            break;
            
        case 'member':
            // Enhanced member content for current members
            $content .= "CURRENT ACTIVE MEMBER: {$entity->full_name}";
            
            if (!empty($entity->party_abbreviation) && !empty($entity->state)) {
                $content .= " ({$entity->party_abbreviation}-{$entity->state})";
            }
            
            $content .= " | SERVING IN CURRENT CONGRESS";
            
            // Add chamber information
            if (!empty($entity->chamber)) {
                $content .= " | CHAMBER: {$entity->chamber}";
            }
            
            // Add biographical info
            if (!empty($entity->biography)) {
                $bio = substr($entity->biography, 0, 200);
                $content .= " | BACKGROUND: {$bio}";
            }
            
            // Add activity keywords
            $content .= " | KEYWORDS: current member, active legislator, serving now";
            
            break;
    }
    
    return $content;
}

echo "Do you want to proceed with regenerating recent embeddings? (y/N): ";

$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'y') {
    echo "Cancelled." . PHP_EOL;
    exit(0);
}

echo "" . PHP_EOL;
echo "2. Starting enhanced embedding generation for recent data..." . PHP_EOL;

// Get the embedding service
try {
    $embeddingService = app('App\Services\DocumentEmbeddingService');
} catch (Exception $e) {
    echo "❌ Could not load DocumentEmbeddingService: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$totalProcessed = 0;
$totalErrors = 0;
$startTime = time();

// Delete existing recent bill embeddings first
echo "   🗑️  Clearing existing recent bill embeddings..." . PHP_EOL;
$deletedRecent = DB::table('embeddings')
    ->whereIn('entity_id', function($query) {
        $query->select('id')
              ->from('bills')
              ->where('introduced_date', '>=', '2024-01-01');
    })
    ->where('entity_type', 'bill')
    ->delete();

echo "   ✅ Cleared $deletedRecent recent bill embeddings" . PHP_EOL;

// Delete existing current member embeddings
$deletedMembers = DB::table('embeddings')
    ->whereIn('entity_id', function($query) {
        $query->select('id')
              ->from('members')
              ->where('current_member', true);
    })
    ->where('entity_type', 'member')
    ->delete();

echo "   ✅ Cleared $deletedMembers current member embeddings" . PHP_EOL;

// Process recent bills (2024+) with enhanced content
echo "" . PHP_EOL;
echo "3. Processing recent bills (2024+) with enhanced date emphasis..." . PHP_EOL;

$processedBills = 0;
DB::table('bills')
    ->select('id', 'type', 'number', 'title', 'introduced_date', 'sponsor_name', 'sponsor_party', 'sponsor_state', 'subjects', 'summary')
    ->where('introduced_date', '>=', '2024-01-01')
    ->orderBy('introduced_date', 'desc')
    ->chunk(25, function ($bills) use ($embeddingService, &$totalProcessed, &$totalErrors, &$processedBills, $startTime) {
        foreach ($bills as $bill) {
            try {
                // Generate enhanced content with strong date emphasis
                $content = generateEnhancedEmbeddingContent($bill, 'bill');
                
                // Generate embedding
                $embedding = $embeddingService->generateEmbedding($content);
                
                if ($embedding) {
                    // Store with enhanced metadata
                    $metadata = [
                        'type' => $bill->type,
                        'number' => $bill->number,
                        'introduced_date' => $bill->introduced_date,
                        'year' => date('Y', strtotime($bill->introduced_date)),
                        'month' => date('n', strtotime($bill->introduced_date)),
                        'is_recent' => true,
                        'is_current_congress' => true,
                        'sponsor_name' => $bill->sponsor_name,
                        'sponsor_party' => $bill->sponsor_party,
                        'sponsor_state' => $bill->sponsor_state,
                        'enhanced_for_recency' => true
                    ];
                    
                    DB::table('embeddings')->insert([
                        'entity_type' => 'bill',
                        'entity_id' => $bill->id,
                        'content' => $content,
                        'embedding' => json_encode($embedding),
                        'metadata' => json_encode($metadata),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $totalProcessed++;
                    $processedBills++;
                    
                    if ($totalProcessed % 10 == 0) {
                        $elapsed = time() - $startTime;
                        $rate = $totalProcessed / max($elapsed, 1);
                        echo "      Processed: $totalProcessed items (Rate: " . round($rate, 1) . "/sec)" . PHP_EOL;
                    }
                } else {
                    $totalErrors++;
                    echo "      ❌ Failed to generate embedding for bill {$bill->id}" . PHP_EOL;
                }
                
            } catch (Exception $e) {
                $totalErrors++;
                echo "      ❌ Error processing bill {$bill->id}: " . $e->getMessage() . PHP_EOL;
            }
        }
    });

echo "   ✅ Processed $processedBills recent bills" . PHP_EOL;

// Process current members
echo "" . PHP_EOL;
echo "4. Processing current members..." . PHP_EOL;

$processedMembers = 0;
DB::table('members')
    ->select('id', 'full_name', 'party_abbreviation', 'state', 'chamber', 'current_member', 'biography')
    ->where('current_member', true)
    ->chunk(25, function ($members) use ($embeddingService, &$totalProcessed, &$totalErrors, &$processedMembers, $startTime) {
        foreach ($members as $member) {
            try {
                // Generate enhanced content
                $content = generateEnhancedEmbeddingContent($member, 'member');
                
                // Generate embedding
                $embedding = $embeddingService->generateEmbedding($content);
                
                if ($embedding) {
                    // Store with enhanced metadata
                    $metadata = [
                        'full_name' => $member->full_name,
                        'party_abbreviation' => $member->party_abbreviation,
                        'state' => $member->state,
                        'chamber' => $member->chamber,
                        'current_member' => true,
                        'is_active' => true,
                        'enhanced_for_activity' => true
                    ];
                    
                    DB::table('embeddings')->insert([
                        'entity_type' => 'member',
                        'entity_id' => $member->id,
                        'content' => $content,
                        'embedding' => json_encode($embedding),
                        'metadata' => json_encode($metadata),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $totalProcessed++;
                    $processedMembers++;
                    
                    if ($totalProcessed % 10 == 0) {
                        $elapsed = time() - $startTime;
                        $rate = $totalProcessed / max($elapsed, 1);
                        echo "      Processed: $totalProcessed items (Rate: " . round($rate, 1) . "/sec)" . PHP_EOL;
                    }
                } else {
                    $totalErrors++;
                    echo "      ❌ Failed to generate embedding for member {$member->id}" . PHP_EOL;
                }
                
            } catch (Exception $e) {
                $totalErrors++;
                echo "      ❌ Error processing member {$member->id}: " . $e->getMessage() . PHP_EOL;
            }
        }
    });

echo "   ✅ Processed $processedMembers current members" . PHP_EOL;

$endTime = time();
$totalTime = $endTime - $startTime;

echo "" . PHP_EOL;
echo "=== Enhanced Embedding Generation Complete ===" . PHP_EOL;
echo "✅ Recent bills processed: $processedBills" . PHP_EOL;
echo "✅ Current members processed: $processedMembers" . PHP_EOL;
echo "✅ Total processed: $totalProcessed" . PHP_EOL;
echo "❌ Total errors: $totalErrors" . PHP_EOL;
echo "⏱️  Total time: " . gmdate("H:i:s", $totalTime) . PHP_EOL;
echo "📊 Average rate: " . round($totalProcessed / max($totalTime, 1), 1) . " embeddings/second" . PHP_EOL;

// Test the enhanced embeddings
echo "" . PHP_EOL;
echo "5. Testing enhanced embeddings..." . PHP_EOL;

// Check for enhanced content
$enhancedBillEmbeddings = DB::table('embeddings')
    ->where('entity_type', 'bill')
    ->where('content', 'LIKE', '%CURRENT CONGRESS%')
    ->count();

$enhancedMemberEmbeddings = DB::table('embeddings')
    ->where('entity_type', 'member')
    ->where('content', 'LIKE', '%CURRENT ACTIVE MEMBER%')
    ->count();

echo "   Enhanced bill embeddings: $enhancedBillEmbeddings" . PHP_EOL;
echo "   Enhanced member embeddings: $enhancedMemberEmbeddings" . PHP_EOL;

// Test semantic search
try {
    $semanticService = app('App\Services\SemanticSearchService');
    
    echo "   Testing semantic search for 'active sponsors 2024'..." . PHP_EOL;
    $searchResults = $semanticService->searchBills('active sponsors 2024 current congress', [
        'limit' => 3,
        'threshold' => 0.3
    ]);
    
    if ($searchResults['success'] && count($searchResults['bills']) > 0) {
        echo "   ✅ Found " . count($searchResults['bills']) . " results:" . PHP_EOL;
        foreach ($searchResults['bills'] as $i => $result) {
            $bill = $result['model'];
            $similarity = $result['similarity'] ?? 0;
            echo "      " . ($i + 1) . ". {$bill->type} {$bill->number} (" . round($similarity, 3) . ") - {$bill->introduced_date}" . PHP_EOL;
        }
    } else {
        echo "   ❌ No semantic search results found" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing semantic search: " . $e->getMessage() . PHP_EOL;
}

// Test the chatbot
echo "" . PHP_EOL;
echo "6. Testing chatbot with enhanced embeddings..." . PHP_EOL;

try {
    // Clear service cache
    app()->forgetInstance('App\Services\CongressChatbotService');
    app()->forgetInstance('App\Services\SemanticSearchService');
    
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "   Testing: 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
    $result = $chatbotService->askQuestion('Who are the most active bill sponsors in 2024?');
    
    if ($result['success']) {
        echo "   ✅ Query successful with method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        
        // Check for improvements
        $mentions2024 = preg_match('/\b2024\b/', $result['response']);
        $mentions2025 = preg_match('/\b2025\b/', $result['response']);
        $mentionsRecent = $mentions2024 || $mentions2025;
        $mentionsCurrent = stripos($result['response'], 'current') !== false;
        
        echo "   " . ($mentionsRecent ? "✅" : "❌") . " Mentions recent years: " . ($mentionsRecent ? "Yes" : "No") . PHP_EOL;
        echo "   " . ($mentionsCurrent ? "✅" : "❌") . " Mentions current activity: " . ($mentionsCurrent ? "Yes" : "No") . PHP_EOL;
        
        if ($mentionsRecent && $mentionsCurrent) {
            echo "   🎉 SUCCESS: Enhanced embeddings are working!" . PHP_EOL;
        }
        
    } else {
        echo "   ❌ Query failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Enhanced recent embeddings with:" . PHP_EOL;
echo "✅ Strong date emphasis (CURRENT CONGRESS 2024/2025)" . PHP_EOL;
echo "✅ Temporal keywords (recent, current, active)" . PHP_EOL;
echo "✅ Enhanced sponsor context for recent bills" . PHP_EOL;
echo "✅ Current member activity emphasis" . PHP_EOL;
echo "✅ Improved metadata for better filtering" . PHP_EOL;
echo "" . PHP_EOL;
echo "🧪 Your chatbot should now prioritize recent data!" . PHP_EOL;
echo "Test with: 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;