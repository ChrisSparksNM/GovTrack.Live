<?php

echo "=== Generate Member and Action Embeddings (Fixed) ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will generate embeddings for members and bill actions using correct database schema." . PHP_EOL;
echo "" . PHP_EOL;

// Check current status
echo "1. Checking current data..." . PHP_EOL;

$totalMembers = DB::table('members')->count();
$currentMembers = DB::table('members')->where('current_member', true)->count();
$totalActions = DB::table('bill_actions')->count();

$existingMemberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();
$existingActionEmbeddings = DB::table('embeddings')->where('entity_type', 'bill_action')->count();

echo "   Data counts:" . PHP_EOL;
echo "   - Total members: $totalMembers" . PHP_EOL;
echo "   - Current members: $currentMembers" . PHP_EOL;
echo "   - Total bill actions: $totalActions" . PHP_EOL;
echo "   - Existing member embeddings: $existingMemberEmbeddings" . PHP_EOL;
echo "   - Existing action embeddings: $existingActionEmbeddings" . PHP_EOL;

$totalToProcess = $totalMembers + $totalActions;
echo "   - Total to process: $totalToProcess" . PHP_EOL;
echo "   ⏱️  Estimated time: " . ceil($totalToProcess / 100 * 2) . " minutes" . PHP_EOL;

echo "" . PHP_EOL;

// Enhanced content generation functions using correct schema
function generateMemberEmbeddingContent($member) {
    $content = '';
    
    // Enhanced member content with current status emphasis
    $content .= "MEMBER: {$member->full_name}";
    
    if (!empty($member->party_abbreviation) && !empty($member->state)) {
        $content .= " ({$member->party_abbreviation}-{$member->state})";
    }
    
    // Emphasize current members
    if ($member->current_member) {
        $content .= " | CURRENT ACTIVE MEMBER";
    } else {
        $content .= " | Former Member";
    }
    
    // Add chamber information
    if (!empty($member->chamber)) {
        $content .= " | Chamber: {$member->chamber}";
    }
    
    // Add district for House members
    if (!empty($member->district)) {
        $content .= " | District: {$member->district}";
    }
    
    // Add party name if different from abbreviation
    if (!empty($member->party_name) && $member->party_name !== $member->party_abbreviation) {
        $content .= " | Party: {$member->party_name}";
    }
    
    // Add legislative activity counts
    if (!empty($member->sponsored_legislation_count)) {
        $content .= " | Sponsored Bills: {$member->sponsored_legislation_count}";
    }
    
    if (!empty($member->cosponsored_legislation_count)) {
        $content .= " | Cosponsored Bills: {$member->cosponsored_legislation_count}";
    }
    
    // Add birth year if available
    if (!empty($member->birth_year)) {
        $content .= " | Born: {$member->birth_year}";
    }
    
    // Add activity keywords
    if ($member->current_member) {
        $content .= " | ACTIVE IN CURRENT CONGRESS | SERVING NOW";
    }
    
    // Add search keywords
    $content .= " | Keywords: legislator, congress member";
    if ($member->current_member) {
        $content .= ", current member, active representative";
    }
    
    if ($member->chamber === 'House') {
        $content .= ", house representative";
    } elseif ($member->chamber === 'Senate') {
        $content .= ", senator";
    }
    
    return $content;
}

function generateActionEmbeddingContent($action) {
    $content = '';
    
    // Start with action information
    $content .= "BILL ACTION: {$action->action_date}";
    
    // Add bill information if available
    if (!empty($action->bill_type) && !empty($action->bill_number)) {
        $content .= " | Bill: {$action->bill_type} {$action->bill_number}";
    }
    
    // Add action text
    if (!empty($action->text)) {
        $content .= " | Action: {$action->text}";
    }
    
    // Add action type if available
    if (!empty($action->type)) {
        $content .= " | Type: {$action->type}";
    }
    
    // Add committee information if available
    if (!empty($action->committees)) {
        $committees = is_string($action->committees) ? $action->committees : json_encode($action->committees);
        $content .= " | Committees: {$committees}";
    }
    
    // Add temporal context
    $year = date('Y', strtotime($action->action_date));
    $isRecent = $year >= 2024;
    
    if ($isRecent) {
        $content .= " | RECENT LEGISLATIVE ACTION";
    }
    
    // Add keywords
    $content .= " | Keywords: legislative action, bill progress, $year";
    if ($isRecent) {
        $content .= ", recent action, current congress";
    }
    
    return $content;
}

// Ask for confirmation
echo "This will:" . PHP_EOL;
echo "✅ Generate embeddings for all $totalMembers members" . PHP_EOL;
echo "✅ Generate embeddings for all $totalActions bill actions" . PHP_EOL;
echo "✅ Use correct database schema (no biography field)" . PHP_EOL;
echo "✅ Emphasize current/active members" . PHP_EOL;
echo "✅ Include temporal context for recent actions" . PHP_EOL;
echo "✅ Add enhanced search keywords" . PHP_EOL;
echo "" . PHP_EOL;
echo "⚠️  This will replace existing member and action embeddings." . PHP_EOL;
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
echo "2. Starting embedding generation..." . PHP_EOL;

// Get the embedding service
try {
    $embeddingService = app('App\Services\EmbeddingService');
} catch (Exception $e) {
    echo "❌ Could not load EmbeddingService: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Clear existing member and action embeddings
echo "   🗑️  Clearing existing member and action embeddings..." . PHP_EOL;
$deletedMembers = DB::table('embeddings')->where('entity_type', 'member')->delete();
$deletedActions = DB::table('embeddings')->where('entity_type', 'bill_action')->delete();
echo "   ✅ Cleared $deletedMembers member embeddings and $deletedActions action embeddings" . PHP_EOL;

$totalProcessed = 0;
$totalErrors = 0;
$startTime = time();

// Process members first
echo "" . PHP_EOL;
echo "3. Processing members..." . PHP_EOL;

$processedMembers = 0;
DB::table('members')
    ->select(
        'id', 
        'bioguide_id',
        'full_name', 
        'party_abbreviation', 
        'party_name',
        'state', 
        'chamber', 
        'district', 
        'current_member',
        'birth_year',
        'sponsored_legislation_count',
        'cosponsored_legislation_count'
    )
    ->orderBy('current_member', 'desc') // Process current members first
    ->chunk(25, function ($members) use ($embeddingService, &$totalProcessed, &$totalErrors, &$processedMembers, $startTime) {
        foreach ($members as $member) {
            try {
                // Generate enhanced content
                $content = generateMemberEmbeddingContent($member);
                
                // Generate embedding
                $embedding = $embeddingService->generateEmbedding($content);
                
                if ($embedding) {
                    // Store with enhanced metadata
                    $metadata = [
                        'bioguide_id' => $member->bioguide_id,
                        'full_name' => $member->full_name,
                        'party_abbreviation' => $member->party_abbreviation,
                        'party_name' => $member->party_name,
                        'state' => $member->state,
                        'chamber' => $member->chamber,
                        'district' => $member->district,
                        'current_member' => $member->current_member,
                        'is_active' => $member->current_member,
                        'sponsored_count' => $member->sponsored_legislation_count,
                        'cosponsored_count' => $member->cosponsored_legislation_count,
                        'enhanced_for_activity' => true
                    ];
                    
                    // Store using the embedding service
                    $success = $embeddingService->storeEmbedding(
                        'member',
                        $member->id,
                        $embedding,
                        $content,
                        $metadata
                    );
                    
                    if ($success) {
                        $totalProcessed++;
                        $processedMembers++;
                    } else {
                        $totalErrors++;
                        echo "      ❌ Failed to store embedding for member {$member->id}" . PHP_EOL;
                    }
                    
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

echo "   ✅ Processed $processedMembers members" . PHP_EOL;

// Process bill actions
echo "" . PHP_EOL;
echo "4. Processing bill actions..." . PHP_EOL;

$processedActions = 0;
DB::table('bill_actions')
    ->leftJoin('bills', 'bill_actions.bill_id', '=', 'bills.id')
    ->select(
        'bill_actions.id',
        'bill_actions.bill_id',
        'bill_actions.action_date',
        'bill_actions.text',
        'bill_actions.type',
        'bill_actions.committees',
        'bills.type as bill_type',
        'bills.number as bill_number'
    )
    ->orderBy('bill_actions.action_date', 'desc') // Process recent actions first
    ->chunk(50, function ($actions) use ($embeddingService, &$totalProcessed, &$totalErrors, &$processedActions, $startTime) {
        foreach ($actions as $action) {
            try {
                // Generate enhanced content
                $content = generateActionEmbeddingContent($action);
                
                // Generate embedding
                $embedding = $embeddingService->generateEmbedding($content);
                
                if ($embedding) {
                    // Store with enhanced metadata
                    $metadata = [
                        'bill_id' => $action->bill_id,
                        'bill_type' => $action->bill_type,
                        'bill_number' => $action->bill_number,
                        'action_date' => $action->action_date,
                        'action_type' => $action->type,
                        'year' => date('Y', strtotime($action->action_date)),
                        'is_recent' => date('Y', strtotime($action->action_date)) >= 2024
                    ];
                    
                    // Store using the embedding service
                    $success = $embeddingService->storeEmbedding(
                        'bill_action',
                        $action->id,
                        $embedding,
                        $content,
                        $metadata
                    );
                    
                    if ($success) {
                        $totalProcessed++;
                        $processedActions++;
                    } else {
                        $totalErrors++;
                        echo "      ❌ Failed to store embedding for action {$action->id}" . PHP_EOL;
                    }
                    
                    if ($totalProcessed % 25 == 0) {
                        $elapsed = time() - $startTime;
                        $rate = $totalProcessed / max($elapsed, 1);
                        echo "      Processed: $totalProcessed items (Rate: " . round($rate, 1) . "/sec)" . PHP_EOL;
                    }
                } else {
                    $totalErrors++;
                    echo "      ❌ Failed to generate embedding for action {$action->id}" . PHP_EOL;
                }
                
            } catch (Exception $e) {
                $totalErrors++;
                echo "      ❌ Error processing action {$action->id}: " . $e->getMessage() . PHP_EOL;
            }
        }
    });

echo "   ✅ Processed $processedActions bill actions" . PHP_EOL;

$endTime = time();
$totalTime = $endTime - $startTime;

echo "" . PHP_EOL;
echo "=== Embedding Generation Complete ===" . PHP_EOL;
echo "✅ Members processed: $processedMembers" . PHP_EOL;
echo "✅ Actions processed: $processedActions" . PHP_EOL;
echo "✅ Total processed: $totalProcessed" . PHP_EOL;
echo "❌ Total errors: $totalErrors" . PHP_EOL;
echo "⏱️  Total time: " . gmdate("H:i:s", $totalTime) . PHP_EOL;
echo "📊 Average rate: " . round($totalProcessed / max($totalTime, 1), 1) . " embeddings/second" . PHP_EOL;

// Verify the new embeddings
echo "" . PHP_EOL;
echo "5. Verifying new embeddings..." . PHP_EOL;

$newMemberEmbeddings = DB::table('embeddings')->where('entity_type', 'member')->count();
$newActionEmbeddings = DB::table('embeddings')->where('entity_type', 'bill_action')->count();
$currentMemberEmbeddings = DB::table('embeddings')
    ->where('entity_type', 'member')
    ->where('content', 'LIKE', '%CURRENT ACTIVE MEMBER%')
    ->count();

echo "   New embedding counts:" . PHP_EOL;
echo "   - Member embeddings: $newMemberEmbeddings" . PHP_EOL;
echo "   - Action embeddings: $newActionEmbeddings" . PHP_EOL;
echo "   - Current member embeddings: $currentMemberEmbeddings" . PHP_EOL;

if ($currentMemberEmbeddings > 0) {
    echo "   ✅ Enhanced member emphasis is working!" . PHP_EOL;
}

// Test semantic search for members
echo "" . PHP_EOL;
echo "6. Testing member search..." . PHP_EOL;

try {
    $semanticService = app('App\Services\SemanticSearchService');
    
    echo "   Testing search for 'current active members'..." . PHP_EOL;
    $searchResults = $semanticService->searchMembers('current active members serving now', [
        'limit' => 3,
        'threshold' => 0.3
    ]);
    
    if ($searchResults['success'] && count($searchResults['members']) > 0) {
        echo "   ✅ Found " . count($searchResults['members']) . " member results:" . PHP_EOL;
        foreach ($searchResults['members'] as $i => $result) {
            $member = $result['model'];
            $similarity = $result['similarity'] ?? 0;
            echo "      " . ($i + 1) . ". {$member->full_name} (" . round($similarity, 3) . ") - {$member->party_abbreviation}-{$member->state}" . PHP_EOL;
        }
    } else {
        echo "   ❌ No member search results found" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing member search: " . $e->getMessage() . PHP_EOL;
}

// Test the chatbot
echo "" . PHP_EOL;
echo "7. Testing chatbot with new member embeddings..." . PHP_EOL;

try {
    // Clear service cache
    app()->forgetInstance('App\Services\CongressChatbotService');
    app()->forgetInstance('App\Services\SemanticSearchService');
    
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "   Testing: 'Who are the current senators from California?'" . PHP_EOL;
    $result = $chatbotService->askQuestion('Who are the current senators from California?');
    
    if ($result['success']) {
        echo "   ✅ Query successful with method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        
        // Check for member mentions
        $mentionsCurrent = stripos($result['response'], 'current') !== false;
        $mentionsSenator = stripos($result['response'], 'senator') !== false;
        $mentionsCalifornia = stripos($result['response'], 'california') !== false;
        
        echo "   " . ($mentionsCurrent ? "✅" : "❌") . " Mentions current: " . ($mentionsCurrent ? "Yes" : "No") . PHP_EOL;
        echo "   " . ($mentionsSenator ? "✅" : "❌") . " Mentions senator: " . ($mentionsSenator ? "Yes" : "No") . PHP_EOL;
        echo "   " . ($mentionsCalifornia ? "✅" : "❌") . " Mentions California: " . ($mentionsCalifornia ? "Yes" : "No") . PHP_EOL;
        
        if ($mentionsCurrent && $mentionsSenator) {
            echo "   🎉 SUCCESS: Member embeddings are working!" . PHP_EOL;
        }
        
        // Show a preview
        echo "   Response preview: " . substr($result['response'], 0, 200) . "..." . PHP_EOL;
        
    } else {
        echo "   ❌ Query failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing chatbot: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Generated embeddings for:" . PHP_EOL;
echo "✅ $processedMembers members with current/active emphasis" . PHP_EOL;
echo "✅ $processedActions bill actions with temporal context" . PHP_EOL;
echo "✅ Enhanced search keywords for better retrieval" . PHP_EOL;
echo "✅ Metadata includes activity and recency flags" . PHP_EOL;
echo "✅ Used correct database schema" . PHP_EOL;
echo "" . PHP_EOL;
echo "🧪 Test these member queries:" . PHP_EOL;
echo "   • 'Who are the current senators from California?'" . PHP_EOL;
echo "   • 'Show me active House members from Texas'" . PHP_EOL;
echo "   • 'Which current members are Democrats?'" . PHP_EOL;
echo "   • 'Find recent legislative actions'" . PHP_EOL;