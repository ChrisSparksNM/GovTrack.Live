<?php

echo "=== Regenerate Embeddings with Date Emphasis ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will regenerate embeddings with enhanced date information to help prioritize recent data." . PHP_EOL;
echo "" . PHP_EOL;

// Check current embedding status
echo "1. Checking current embedding status..." . PHP_EOL;

$totalBills = DB::table('bills')->count();
$totalMembers = DB::table('members')->count();
$totalEmbeddings = DB::table('embeddings')->count();
$billEmbeddings = DB::table('embeddings')->where('entity_type', 'bill')->count();
$memberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();

echo "   Current data:" . PHP_EOL;
echo "   - Bills: $totalBills" . PHP_EOL;
echo "   - Members: $totalMembers" . PHP_EOL;
echo "   - Total embeddings: $totalEmbeddings" . PHP_EOL;
echo "   - Bill embeddings: $billEmbeddings" . PHP_EOL;
echo "   - Member embeddings: $memberEmbeddings" . PHP_EOL;

// Check recent data distribution
$recentBills = DB::table('bills')->where('introduced_date', '>=', '2024-01-01')->count();
$recentBillEmbeddings = DB::table('embeddings')
    ->join('bills', function($join) {
        $join->on('embeddings.entity_id', '=', 'bills.id')
             ->where('embeddings.entity_type', '=', 'bill');
    })
    ->where('bills.introduced_date', '>=', '2024-01-01')
    ->count();

echo "   Recent data (2024+):" . PHP_EOL;
echo "   - Recent bills: $recentBills" . PHP_EOL;
echo "   - Recent bill embeddings: $recentBillEmbeddings" . PHP_EOL;

echo "" . PHP_EOL;

// Enhanced embedding generation function
function generateEnhancedEmbeddingContent($entity, $entityType) {
    $content = '';
    
    switch ($entityType) {
        case 'bill':
            // Start with date emphasis for bills
            $year = date('Y', strtotime($entity->introduced_date));
            $isRecent = $year >= 2024;
            
            if ($isRecent) {
                $content .= "RECENT $year BILL: ";
            } else {
                $content .= "$year BILL: ";
            }
            
            $content .= "{$entity->type} {$entity->number} - {$entity->title}";
            
            // Add date information prominently
            $content .= " | Introduced: {$entity->introduced_date} ({$year})";
            
            // Add sponsor information with emphasis on recent activity
            if (!empty($entity->sponsor_name)) {
                $content .= " | Primary Sponsor: {$entity->sponsor_name}";
                if (!empty($entity->sponsor_party) && !empty($entity->sponsor_state)) {
                    $content .= " ({$entity->sponsor_party}-{$entity->sponsor_state})";
                }
            }
            
            // Add subject areas
            if (!empty($entity->subjects)) {
                $subjects = is_string($entity->subjects) ? $entity->subjects : implode(', ', $entity->subjects);
                $content .= " | Topics: {$subjects}";
            }
            
            // Add summary if available
            if (!empty($entity->summary)) {
                $summary = substr($entity->summary, 0, 300);
                $content .= " | Summary: {$summary}";
            }
            
            // Add recent activity emphasis
            if ($isRecent) {
                $content .= " | CURRENT CONGRESS ACTIVITY";
            }
            
            break;
            
        case 'member':
            // Enhanced member content with current status emphasis
            $content .= "MEMBER: {$entity->full_name}";
            
            if (!empty($entity->party_abbreviation) && !empty($entity->state)) {
                $content .= " ({$entity->party_abbreviation}-{$entity->state})";
            }
            
            // Emphasize current members
            if ($entity->current_member) {
                $content .= " | CURRENT MEMBER";
            } else {
                $content .= " | Former Member";
            }
            
            // Add chamber information
            if (!empty($entity->chamber)) {
                $content .= " | Chamber: {$entity->chamber}";
            }
            
            // Add biographical info
            if (!empty($entity->biography)) {
                $bio = substr($entity->biography, 0, 200);
                $content .= " | Bio: {$bio}";
            }
            
            // Add recent activity context
            if ($entity->current_member) {
                $content .= " | ACTIVE IN CURRENT CONGRESS";
            }
            
            break;
    }
    
    return $content;
}

// Ask user for confirmation
echo "This will:" . PHP_EOL;
echo "✅ Regenerate ALL embeddings with enhanced date emphasis" . PHP_EOL;
echo "✅ Prioritize recent bills (2024+) in embedding content" . PHP_EOL;
echo "✅ Add temporal keywords to improve recent data retrieval" . PHP_EOL;
echo "✅ Enhance sponsor information with current activity context" . PHP_EOL;
echo "" . PHP_EOL;
echo "⚠️  This will replace your existing $totalEmbeddings embeddings." . PHP_EOL;
echo "⏱️  Estimated time: " . ceil(($totalBills + $totalMembers) / 100 * 2) . " minutes" . PHP_EOL;
echo "" . PHP_EOL;
echo "Do you want to proceed? (y/N): ";

$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'y') {
    echo "Cancelled." . PHP_EOL;
    exit(0);
}

echo "" . PHP_EOL;
echo "2. Starting enhanced embedding generation..." . PHP_EOL;

// Get the embedding service
try {
    $embeddingService = app('App\Services\DocumentEmbeddingService');
} catch (Exception $e) {
    echo "❌ Could not load DocumentEmbeddingService: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Clear existing embeddings
echo "   🗑️  Clearing existing embeddings..." . PHP_EOL;
DB::table('embeddings')->delete();
echo "   ✅ Cleared $totalEmbeddings existing embeddings" . PHP_EOL;

$totalProcessed = 0;
$totalErrors = 0;
$startTime = time();

// Process bills with enhanced date emphasis
echo "" . PHP_EOL;
echo "3. Processing bills with date emphasis..." . PHP_EOL;

$billChunks = DB::table('bills')
    ->select('id', 'type', 'number', 'title', 'introduced_date', 'sponsor_name', 'sponsor_party', 'sponsor_state', 'subjects', 'summary')
    ->orderBy('introduced_date', 'desc') // Process recent bills first
    ->chunk(50, function ($bills) use ($embeddingService, &$totalProcessed, &$totalErrors) {
        foreach ($bills as $bill) {
            try {
                // Generate enhanced content
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
                        'is_recent' => date('Y', strtotime($bill->introduced_date)) >= 2024,
                        'sponsor_name' => $bill->sponsor_name,
                        'sponsor_party' => $bill->sponsor_party,
                        'sponsor_state' => $bill->sponsor_state
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
                    
                    if ($totalProcessed % 25 == 0) {
                        $elapsed = time() - $GLOBALS['startTime'];
                        $rate = $totalProcessed / max($elapsed, 1);
                        echo "      Processed: $totalProcessed bills (Rate: " . round($rate, 1) . "/sec)" . PHP_EOL;
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

echo "   ✅ Processed $totalProcessed bills with $totalErrors errors" . PHP_EOL;

// Process members
echo "" . PHP_EOL;
echo "4. Processing members..." . PHP_EOL;

$memberChunks = DB::table('members')
    ->select('id', 'full_name', 'party_abbreviation', 'state', 'chamber', 'current_member', 'biography')
    ->orderBy('current_member', 'desc') // Process current members first
    ->chunk(50, function ($members) use ($embeddingService, &$totalProcessed, &$totalErrors) {
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
                        'current_member' => $member->current_member
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
                    
                    if ($totalProcessed % 25 == 0) {
                        $elapsed = time() - $GLOBALS['startTime'];
                        $rate = $totalProcessed / max($elapsed, 1);
                        echo "      Processed: $totalProcessed total (Rate: " . round($rate, 1) . "/sec)" . PHP_EOL;
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

$endTime = time();
$totalTime = $endTime - $startTime;

echo "" . PHP_EOL;
echo "=== Embedding Generation Complete ===" . PHP_EOL;
echo "✅ Total processed: $totalProcessed" . PHP_EOL;
echo "❌ Total errors: $totalErrors" . PHP_EOL;
echo "⏱️  Total time: " . gmdate("H:i:s", $totalTime) . PHP_EOL;
echo "📊 Average rate: " . round($totalProcessed / max($totalTime, 1), 1) . " embeddings/second" . PHP_EOL;

// Verify the new embeddings
echo "" . PHP_EOL;
echo "5. Verifying new embeddings..." . PHP_EOL;

$newTotalEmbeddings = DB::table('embeddings')->count();
$newBillEmbeddings = DB::table('embeddings')->where('entity_type', 'bill')->count();
$newMemberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();

echo "   New embedding counts:" . PHP_EOL;
echo "   - Total: $newTotalEmbeddings" . PHP_EOL;
echo "   - Bills: $newBillEmbeddings" . PHP_EOL;
echo "   - Members: $newMemberEmbeddings" . PHP_EOL;

// Test a sample of recent embeddings
$recentEmbeddings = DB::table('embeddings')
    ->where('entity_type', 'bill')
    ->where('content', 'LIKE', '%RECENT 2024%')
    ->orWhere('content', 'LIKE', '%RECENT 2025%')
    ->count();

echo "   - Recent bill embeddings (2024+): $recentEmbeddings" . PHP_EOL;

if ($recentEmbeddings > 0) {
    echo "   ✅ Enhanced date emphasis is working!" . PHP_EOL;
} else {
    echo "   ⚠️  No recent bill embeddings found - check date formatting" . PHP_EOL;
}

// Test the chatbot with enhanced embeddings
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
        
        // Check for recent year mentions
        $mentions2024 = preg_match('/\b2024\b/', $result['response']);
        $mentions2025 = preg_match('/\b2025\b/', $result['response']);
        $mentionsRecent = $mentions2024 || $mentions2025;
        
        echo "   " . ($mentionsRecent ? "✅" : "❌") . " Mentions recent years: " . ($mentionsRecent ? "Yes" : "No") . PHP_EOL;
        
        // Check for specific sponsor names from our data
        $mentionsSponsors = 0;
        $topSponsors = ['Markey', 'Scott', 'Cornyn', 'Blackburn', 'Merkley'];
        foreach ($topSponsors as $sponsor) {
            if (stripos($result['response'], $sponsor) !== false) {
                $mentionsSponsors++;
            }
        }
        
        echo "   " . ($mentionsSponsors > 0 ? "✅" : "❌") . " Mentions recent sponsors: $mentionsSponsors/5" . PHP_EOL;
        
        if ($mentionsRecent && $mentionsSponsors > 0) {
            echo "   🎉 SUCCESS: Enhanced embeddings are working!" . PHP_EOL;
        } else {
            echo "   ⚠️  May need additional tuning" . PHP_EOL;
        }
        
    } else {
        echo "   ❌ Query failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Enhanced embedding features:" . PHP_EOL;
echo "✅ Date emphasis in embedding content (RECENT 2024/2025)" . PHP_EOL;
echo "✅ Temporal keywords for better recent data retrieval" . PHP_EOL;
echo "✅ Enhanced sponsor information with current activity context" . PHP_EOL;
echo "✅ Metadata includes year and recency flags" . PHP_EOL;
echo "✅ Processing prioritized recent bills first" . PHP_EOL;
echo "" . PHP_EOL;
echo "🧪 Test your enhanced chatbot:" . PHP_EOL;
echo "   • 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
echo "   • 'Show me recent healthcare bills'" . PHP_EOL;
echo "   • 'What current members are most active?'" . PHP_EOL;
echo "   • 'Find bills introduced this year'" . PHP_EOL;