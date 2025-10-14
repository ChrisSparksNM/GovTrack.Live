<?php

echo "=== Fix Chatbot Recent Data Issues ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will fix your chatbot to prioritize recent/current data over historical data." . PHP_EOL;
echo "" . PHP_EOL;

// Test current behavior first
echo "1. Testing current chatbot behavior..." . PHP_EOL;

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "   Query: 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
    $result = $chatbotService->askQuestion('Who are the most active bill sponsors in 2024?');
    
    if ($result['success']) {
        echo "   ✅ Current method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        
        // Check if response mentions recent years
        $mentions2024 = preg_match('/\b2024\b/', $result['response']);
        $mentions2025 = preg_match('/\b2025\b/', $result['response']);
        $mentionsRecent = $mentions2024 || $mentions2025;
        
        echo "   " . ($mentionsRecent ? "✅" : "❌") . " Response mentions recent years: " . ($mentionsRecent ? "Yes" : "No") . PHP_EOL;
        
        // Check for specific recent bill references
        $recentBills = preg_match_all('/\b(HR|S)\s+\d+\b/', $result['response'], $matches);
        echo "   " . ($recentBills > 0 ? "✅" : "❌") . " Bill references found: $recentBills" . PHP_EOL;
        
    } else {
        echo "   ❌ Query failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;

// Show what data we actually have
echo "2. Checking your actual recent data..." . PHP_EOL;

$recentSponsors = DB::table('bills')
    ->join('bill_sponsors', 'bills.id', '=', 'bill_sponsors.bill_id')
    ->where('bills.introduced_date', '>=', '2024-01-01')
    ->select(
        'bill_sponsors.full_name',
        'bill_sponsors.party',
        'bill_sponsors.state',
        DB::raw('COUNT(*) as bill_count'),
        DB::raw('MAX(bills.introduced_date) as latest_bill')
    )
    ->groupBy('bill_sponsors.full_name', 'bill_sponsors.party', 'bill_sponsors.state')
    ->orderBy('bill_count', 'desc')
    ->limit(10)
    ->get();

if ($recentSponsors->count() > 0) {
    echo "   ✅ Most active sponsors in 2024+:" . PHP_EOL;
    foreach ($recentSponsors->take(5) as $sponsor) {
        echo "      {$sponsor->full_name} ({$sponsor->party}-{$sponsor->state}): {$sponsor->bill_count} bills" . PHP_EOL;
    }
} else {
    echo "   ❌ No recent sponsor data found" . PHP_EOL;
    exit(1);
}

echo "" . PHP_EOL;

// Apply the fixes
echo "3. Applying fixes to prioritize recent data..." . PHP_EOL;

// Fix 1: Update SemanticSearchService to lower threshold and add date filtering
echo "   🔧 Fix 1: Updating SemanticSearchService for better recent data search..." . PHP_EOL;

$semanticSearchPath = 'app/Services/SemanticSearchService.php';
$semanticContent = file_get_contents($semanticSearchPath);

// Lower the default threshold from 0.7 to 0.5 for more results
if (strpos($semanticContent, "threshold' ?? 0.7") !== false) {
    $semanticContent = str_replace(
        "\$threshold = \$options['threshold'] ?? 0.7;",
        "\$threshold = \$options['threshold'] ?? 0.5;",
        $semanticContent
    );
    echo "      ✅ Lowered default similarity threshold to 0.5" . PHP_EOL;
}

// Lower bill search threshold from 0.6 to 0.4
if (strpos($semanticContent, "'threshold' => \$filters['threshold'] ?? 0.6") !== false) {
    $semanticContent = str_replace(
        "'threshold' => \$filters['threshold'] ?? 0.6",
        "'threshold' => \$filters['threshold'] ?? 0.4",
        $semanticContent
    );
    echo "      ✅ Lowered bill search threshold to 0.4" . PHP_EOL;
}

// Add date-based sorting method
$dateSortingMethod = '
    /**
     * Sort results by recency, prioritizing recent bills
     */
    private function sortByRecency(array $results): array
    {
        usort($results, function($a, $b) {
            // First sort by similarity (high to low)
            $similarityDiff = $b[\'similarity\'] <=> $a[\'similarity\'];
            
            // If similarity is close (within 0.1), prioritize by date
            if (abs($a[\'similarity\'] - $b[\'similarity\']) < 0.1) {
                $aDate = null;
                $bDate = null;
                
                // Extract dates from models
                if (isset($a[\'model\']) && method_exists($a[\'model\'], \'getAttribute\')) {
                    $aDate = $a[\'model\']->introduced_date ?? $a[\'model\']->created_at ?? null;
                }
                if (isset($b[\'model\']) && method_exists($b[\'model\'], \'getAttribute\')) {
                    $bDate = $b[\'model\']->introduced_date ?? $b[\'model\']->created_at ?? null;
                }
                
                // If both have dates, sort by date (recent first)
                if ($aDate && $bDate) {
                    return $bDate <=> $aDate;
                }
                
                // If only one has a date, prioritize it
                if ($aDate && !$bDate) return -1;
                if (!$aDate && $bDate) return 1;
            }
            
            return $similarityDiff;
        });
        
        return $results;
    }';

// Add the method before the last closing brace
if (strpos($semanticContent, 'sortByRecency') === false) {
    $semanticContent = str_replace(
        "\n}\n",
        $dateSortingMethod . "\n}\n",
        $semanticContent
    );
    echo "      ✅ Added date-based sorting method" . PHP_EOL;
}

// Update the searchBills method to use recency sorting
$searchBillsPattern = '/(\$filteredResults = \$this->applyBillFilters\(\$searchResults\[\'results\'\], \$filters\);)/';
if (preg_match($searchBillsPattern, $semanticContent)) {
    $semanticContent = preg_replace(
        $searchBillsPattern,
        '$1' . "\n        \n        // Sort by recency to prioritize recent bills\n        \$filteredResults = \$this->sortByRecency(\$filteredResults);",
        $semanticContent
    );
    echo "      ✅ Added recency sorting to bill search" . PHP_EOL;
}

file_put_contents($semanticSearchPath, $semanticContent);

// Fix 2: Update CongressChatbotService to use lower thresholds for recent queries
echo "   🔧 Fix 2: Updating CongressChatbotService for better recent data handling..." . PHP_EOL;

$chatbotPath = 'app/Services/CongressChatbotService.php';
$chatbotContent = file_get_contents($chatbotPath);

// Update semantic search thresholds in the chatbot service
if (strpos($chatbotContent, "'threshold' => 0.6") !== false) {
    $chatbotContent = str_replace(
        "'threshold' => 0.6",
        "'threshold' => 0.4",
        $chatbotContent
    );
    echo "      ✅ Lowered chatbot semantic search threshold to 0.4" . PHP_EOL;
}

// Update Claude semantic threshold too
if (strpos($chatbotContent, "'threshold' => 0.3") !== false) {
    $chatbotContent = str_replace(
        "'threshold' => 0.3",
        "'threshold' => 0.2",
        $chatbotContent
    );
    echo "      ✅ Lowered Claude semantic threshold to 0.2" . PHP_EOL;
}

file_put_contents($chatbotPath, $chatbotContent);

// Fix 3: Add recent data context to prompts
echo "   🔧 Fix 3: Adding recent data emphasis to AI prompts..." . PHP_EOL;

// Look for prompt building methods and add recent data emphasis
$recentDataPrompt = '
        // Add emphasis on recent/current data
        if (preg_match(\'/\b(recent|current|2024|2025|latest|active|now)\b/i\', $question)) {
            $prompt .= "\n\nIMPORTANT: The user is asking about RECENT/CURRENT data. Please prioritize information from 2024-2025 over historical data. Focus on the most recent congressional session and current activity.";
        }';

// Add this to the buildClaudeEnhancedPrompt method if it exists
if (strpos($chatbotContent, 'buildClaudeEnhancedPrompt') !== false) {
    $pattern = '/(\$prompt = .*?;)/s';
    if (preg_match($pattern, $chatbotContent)) {
        $chatbotContent = preg_replace(
            '/(\$prompt = .*?;)(\s*\n\s*\$response)/s',
            '$1' . $recentDataPrompt . '$2',
            $chatbotContent
        );
        echo "      ✅ Added recent data emphasis to Claude prompts" . PHP_EOL;
    }
}

file_put_contents($chatbotPath, $chatbotContent);

echo "" . PHP_EOL;

// Test the fixes
echo "4. Testing the fixes..." . PHP_EOL;

try {
    // Clear any cached services
    app()->forgetInstance('App\Services\SemanticSearchService');
    app()->forgetInstance('App\Services\CongressChatbotService');
    
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "   Testing: 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
    $result = $chatbotService->askQuestion('Who are the most active bill sponsors in 2024?');
    
    if ($result['success']) {
        echo "   ✅ Query successful with method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        
        // Check if response now mentions recent data
        $mentions2024 = preg_match('/\b2024\b/', $result['response']);
        $mentions2025 = preg_match('/\b2025\b/', $result['response']);
        $mentionsRecent = $mentions2024 || $mentions2025;
        
        echo "   " . ($mentionsRecent ? "✅" : "❌") . " Now mentions recent years: " . ($mentionsRecent ? "Yes" : "No") . PHP_EOL;
        
        // Check for recent sponsor names
        $mentionsRecentSponsors = 0;
        foreach ($recentSponsors->take(3) as $sponsor) {
            $lastName = explode(' ', $sponsor->full_name);
            $lastName = end($lastName);
            if (stripos($result['response'], $lastName) !== false) {
                $mentionsRecentSponsors++;
            }
        }
        
        echo "   " . ($mentionsRecentSponsors > 0 ? "✅" : "❌") . " Mentions recent sponsors: $mentionsRecentSponsors/3" . PHP_EOL;
        
        if ($mentionsRecentSponsors > 0 && $mentionsRecent) {
            echo "   🎉 SUCCESS: Chatbot is now finding recent data!" . PHP_EOL;
        } else {
            echo "   ⚠️  Partial improvement - may need additional tuning" . PHP_EOL;
        }
        
    } else {
        echo "   ❌ Query still failing: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing fixes: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;

// Test semantic search directly
echo "5. Testing semantic search improvements..." . PHP_EOL;

try {
    $semanticService = app('App\Services\SemanticSearchService');
    
    $searchResults = $semanticService->searchBills('active sponsors 2024 recent bills', [
        'limit' => 5,
        'threshold' => 0.4
    ]);
    
    if ($searchResults['success'] && count($searchResults['bills']) > 0) {
        echo "   ✅ Semantic search found " . count($searchResults['bills']) . " results:" . PHP_EOL;
        
        $recentCount = 0;
        foreach ($searchResults['bills'] as $i => $result) {
            $bill = $result['model'];
            $similarity = $result['similarity'] ?? 0;
            $isRecent = $bill->introduced_date >= '2024-01-01';
            if ($isRecent) $recentCount++;
            
            echo "      " . ($i + 1) . ". {$bill->type} {$bill->number} (" . round($similarity, 3) . ") - {$bill->introduced_date}" . ($isRecent ? " ✅" : "") . PHP_EOL;
        }
        
        echo "   Recent results: $recentCount / " . count($searchResults['bills']) . PHP_EOL;
        
        if ($recentCount > 0) {
            echo "   🎉 Semantic search is now finding recent bills!" . PHP_EOL;
        }
        
    } else {
        echo "   ❌ Semantic search found no results" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing semantic search: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Applied fixes:" . PHP_EOL;
echo "✅ Lowered similarity thresholds to find more results" . PHP_EOL;
echo "✅ Added date-based sorting to prioritize recent bills" . PHP_EOL;
echo "✅ Enhanced prompts to emphasize recent data requests" . PHP_EOL;
echo "✅ Updated both semantic search and chatbot services" . PHP_EOL;
echo "" . PHP_EOL;
echo "🧪 Test your chatbot now with:" . PHP_EOL;
echo "   • 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
echo "   • 'Show me recent healthcare bills'" . PHP_EOL;
echo "   • 'What bills were introduced this year?'" . PHP_EOL;
echo "" . PHP_EOL;
echo "If you still get historical data, you may need to:" . PHP_EOL;
echo "1. Regenerate embeddings with better date emphasis" . PHP_EOL;
echo "2. Further lower similarity thresholds" . PHP_EOL;
echo "3. Add explicit date filtering to search queries" . PHP_EOL;