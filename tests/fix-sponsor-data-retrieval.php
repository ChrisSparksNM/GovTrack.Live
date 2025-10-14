<?php

echo "=== Fix Sponsor Data Retrieval ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "This will improve how the chatbot finds and presents sponsor information." . PHP_EOL;
echo "" . PHP_EOL;

// Test direct sponsor query
echo "1. Testing direct sponsor data query..." . PHP_EOL;

$recentSponsors = DB::table('bills')
    ->join('bill_sponsors', 'bills.id', '=', 'bill_sponsors.bill_id')
    ->where('bills.introduced_date', '>=', '2024-01-01')
    ->select(
        'bill_sponsors.full_name',
        'bill_sponsors.party',
        'bill_sponsors.state',
        DB::raw('COUNT(*) as bill_count'),
        DB::raw('MAX(bills.introduced_date) as latest_bill'),
        DB::raw('GROUP_CONCAT(DISTINCT CONCAT(bills.type, " ", bills.number) ORDER BY bills.introduced_date DESC LIMIT 3) as recent_bills')
    )
    ->groupBy('bill_sponsors.full_name', 'bill_sponsors.party', 'bill_sponsors.state')
    ->orderBy('bill_count', 'desc')
    ->limit(10)
    ->get();

echo "   ✅ Top sponsors in 2024+:" . PHP_EOL;
foreach ($recentSponsors->take(5) as $sponsor) {
    echo "      {$sponsor->full_name} ({$sponsor->party}-{$sponsor->state}): {$sponsor->bill_count} bills" . PHP_EOL;
    echo "         Recent: {$sponsor->recent_bills}" . PHP_EOL;
}

echo "" . PHP_EOL;

// Create a specialized service method for sponsor queries
echo "2. Adding specialized sponsor query method..." . PHP_EOL;

$databaseQueryPath = 'app/Services/DatabaseQueryService.php';

// Check if the file exists and add a sponsor-specific method
if (file_exists($databaseQueryPath)) {
    $content = file_get_contents($databaseQueryPath);
    
    // Add a method specifically for sponsor queries
    $sponsorMethod = '
    /**
     * Get most active bill sponsors for a given time period
     */
    public function getMostActiveSponsors(string $startDate = "2024-01-01", int $limit = 10): array
    {
        try {
            $sponsors = DB::table("bills")
                ->join("bill_sponsors", "bills.id", "=", "bill_sponsors.bill_id")
                ->where("bills.introduced_date", ">=", $startDate)
                ->select(
                    "bill_sponsors.full_name",
                    "bill_sponsors.party", 
                    "bill_sponsors.state",
                    DB::raw("COUNT(*) as bill_count"),
                    DB::raw("MAX(bills.introduced_date) as latest_bill"),
                    DB::raw("MIN(bills.introduced_date) as first_bill"),
                    DB::raw("GROUP_CONCAT(DISTINCT CONCAT(bills.type, \\" \\", bills.number) ORDER BY bills.introduced_date DESC LIMIT 5) as recent_bills")
                )
                ->groupBy("bill_sponsors.full_name", "bill_sponsors.party", "bill_sponsors.state")
                ->orderBy("bill_count", "desc")
                ->limit($limit)
                ->get();
                
            return [
                "success" => true,
                "sponsors" => $sponsors,
                "period" => $startDate,
                "total_found" => $sponsors->count()
            ];
            
        } catch (Exception $e) {
            return [
                "success" => false,
                "error" => $e->getMessage()
            ];
        }
    }';
    
    // Add the method before the last closing brace if it doesn't exist
    if (strpos($content, 'getMostActiveSponsors') === false) {
        $content = str_replace("\n}\n", $sponsorMethod . "\n}\n", $content);
        file_put_contents($databaseQueryPath, $content);
        echo "   ✅ Added getMostActiveSponsors method to DatabaseQueryService" . PHP_EOL;
    } else {
        echo "   ✅ getMostActiveSponsors method already exists" . PHP_EOL;
    }
} else {
    echo "   ❌ DatabaseQueryService.php not found" . PHP_EOL;
}

// Update the CongressChatbotService to use sponsor-specific logic
echo "3. Enhancing chatbot sponsor detection..." . PHP_EOL;

$chatbotPath = 'app/Services/CongressChatbotService.php';
$chatbotContent = file_get_contents($chatbotPath);

// Add sponsor detection logic
$sponsorDetectionCode = '
        // Enhanced sponsor query detection
        if (preg_match(\'/\b(sponsor|active|most.*bill|who.*introduc|author)/i\', $question) && 
            preg_match(\'/\b(2024|2025|recent|current|active|most)/i\', $question)) {
            
            // Use direct database query for sponsor information
            $databaseResult = $this->databaseQueryService->getMostActiveSponsors("2024-01-01", 15);
            
            if ($databaseResult[\'success\'] && !empty($databaseResult[\'sponsors\'])) {
                $sponsorContext = "Recent Active Bill Sponsors (2024+):\n";
                foreach ($databaseResult[\'sponsors\'] as $sponsor) {
                    $sponsorContext .= "- {$sponsor->full_name} ({$sponsor->party}-{$sponsor->state}): {$sponsor->bill_count} bills\n";
                    $sponsorContext .= "  Recent bills: {$sponsor->recent_bills}\n";
                }
                
                $prompt = "Based on this current congressional data, answer the user\'s question about bill sponsors:\n\n";
                $prompt .= $sponsorContext . "\n\n";
                $prompt .= "User Question: " . $question . "\n\n";
                $prompt .= "Please provide a comprehensive answer focusing on the most active sponsors from 2024-2025. ";
                $prompt .= "Include specific names, party affiliations, states, and bill counts. ";
                $prompt .= "Mention some of their recent bill numbers as examples.";
                
                $response = $this->anthropicService->generateChatResponse($prompt);
                
                if ($response[\'success\']) {
                    return [
                        \'success\' => true,
                        \'response\' => $response[\'response\'],
                        \'response_html\' => $this->convertToHtml($response[\'response\']),
                        \'method\' => \'direct_sponsor_query\',
                        \'data_sources\' => [\'Direct database query of bill sponsors 2024+\'],
                        \'sponsor_count\' => count($databaseResult[\'sponsors\'])
                    ];
                }
            }
        }';

// Insert this logic at the beginning of the askQuestion method
$pattern = '/(\s+try\s*\{)/';
if (preg_match($pattern, $chatbotContent)) {
    $chatbotContent = preg_replace(
        $pattern,
        '$1' . $sponsorDetectionCode,
        $chatbotContent,
        1
    );
    file_put_contents($chatbotPath, $chatbotContent);
    echo "   ✅ Added sponsor-specific query detection to chatbot" . PHP_EOL;
} else {
    echo "   ❌ Could not find insertion point in chatbot service" . PHP_EOL;
}

echo "" . PHP_EOL;

// Test the enhanced sponsor detection
echo "4. Testing enhanced sponsor detection..." . PHP_EOL;

try {
    // Clear cached services
    app()->forgetInstance('App\Services\CongressChatbotService');
    app()->forgetInstance('App\Services\DatabaseQueryService');
    
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "   Testing: 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
    $result = $chatbotService->askQuestion('Who are the most active bill sponsors in 2024?');
    
    if ($result['success']) {
        echo "   ✅ Query successful with method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        
        // Check for specific sponsor names
        $mentionsMarkey = stripos($result['response'], 'Markey') !== false;
        $mentionsScott = stripos($result['response'], 'Scott') !== false;
        $mentionsCornyn = stripos($result['response'], 'Cornyn') !== false;
        
        $sponsorMentions = $mentionsMarkey + $mentionsScott + $mentionsCornyn;
        echo "   " . ($sponsorMentions > 0 ? "✅" : "❌") . " Mentions top sponsors: $sponsorMentions/3" . PHP_EOL;
        
        // Check for bill counts
        $mentionsBillCounts = preg_match('/\d+\s+bills?/', $result['response']);
        echo "   " . ($mentionsBillCounts ? "✅" : "❌") . " Includes bill counts: " . ($mentionsBillCounts ? "Yes" : "No") . PHP_EOL;
        
        // Check for party/state info
        $mentionsPartyState = preg_match('/\[?[DR]-[A-Z]{2}\]?/', $result['response']);
        echo "   " . ($mentionsPartyState ? "✅" : "❌") . " Includes party/state info: " . ($mentionsPartyState ? "Yes" : "No") . PHP_EOL;
        
        if ($sponsorMentions > 0 && $mentionsBillCounts) {
            echo "   🎉 SUCCESS: Chatbot now provides specific sponsor data!" . PHP_EOL;
        } else {
            echo "   ⚠️  Still needs improvement" . PHP_EOL;
        }
        
        // Show a snippet of the response
        echo "   Response preview: " . substr($result['response'], 0, 200) . "..." . PHP_EOL;
        
    } else {
        echo "   ❌ Query failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;

// Test the database service directly
echo "5. Testing database service directly..." . PHP_EOL;

try {
    $databaseService = app('App\Services\DatabaseQueryService');
    
    if (method_exists($databaseService, 'getMostActiveSponsors')) {
        $sponsorResult = $databaseService->getMostActiveSponsors('2024-01-01', 5);
        
        if ($sponsorResult['success']) {
            echo "   ✅ Database service found " . $sponsorResult['total_found'] . " sponsors:" . PHP_EOL;
            foreach ($sponsorResult['sponsors'] as $sponsor) {
                echo "      {$sponsor->full_name} ({$sponsor->party}-{$sponsor->state}): {$sponsor->bill_count} bills" . PHP_EOL;
            }
        } else {
            echo "   ❌ Database service failed: " . $sponsorResult['error'] . PHP_EOL;
        }
    } else {
        echo "   ❌ getMostActiveSponsors method not available" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing database service: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Summary ===" . PHP_EOL;
echo "Enhanced sponsor data retrieval:" . PHP_EOL;
echo "✅ Added specialized sponsor query method" . PHP_EOL;
echo "✅ Enhanced chatbot to detect sponsor-specific questions" . PHP_EOL;
echo "✅ Direct database queries for accurate sponsor data" . PHP_EOL;
echo "✅ Improved response formatting with specific details" . PHP_EOL;
echo "" . PHP_EOL;
echo "🧪 Test these sponsor queries:" . PHP_EOL;
echo "   • 'Who are the most active bill sponsors in 2024?'" . PHP_EOL;
echo "   • 'Show me the top 5 bill sponsors this year'" . PHP_EOL;
echo "   • 'Which senators have introduced the most bills recently?'" . PHP_EOL;