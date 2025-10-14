<?php

echo "=== Test Current Embeddings ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$currentEmbeddings = DB::table('embeddings')->count();
echo "Current embeddings: $currentEmbeddings" . PHP_EOL;

if ($currentEmbeddings > 0) {
    echo "" . PHP_EOL;
    echo "Testing if your current embeddings work..." . PHP_EOL;
    
    // Test semantic search directly
    try {
        echo "1. Testing semantic search..." . PHP_EOL;
        $semanticService = app('App\Services\SemanticSearchService');
        $results = $semanticService->searchBills('healthcare', ['limit' => 3]);
        
        if ($results['success'] && count($results['bills']) > 0) {
            echo "✅ Semantic search working - found " . count($results['bills']) . " healthcare bills:" . PHP_EOL;
            foreach ($results['bills'] as $i => $result) {
                $bill = $result['model'];
                echo "   " . ($i + 1) . ". " . $bill->type . " " . $bill->number . ": " . substr($bill->title, 0, 60) . "..." . PHP_EOL;
            }
        } else {
            echo "❌ Semantic search not finding results" . PHP_EOL;
        }
        
        echo "" . PHP_EOL;
        
        // Test full chatbot
        echo "2. Testing full chatbot..." . PHP_EOL;
        $chatbotService = app('App\Services\CongressChatbotService');
        $result = $chatbotService->askQuestion('What healthcare bills have been introduced recently?');
        
        if ($result['success']) {
            echo "✅ Chatbot working!" . PHP_EOL;
            echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
            echo "Response length: " . strlen($result['response']) . " chars" . PHP_EOL;
            
            // Check if response contains specific bill references
            if (preg_match('/\b(HR|S)\s+\d+/', $result['response'])) {
                echo "✅ Response contains specific bill references!" . PHP_EOL;
                echo "" . PHP_EOL;
                echo "🎉 YOUR CHATBOT IS ALREADY WORKING!" . PHP_EOL;
                echo "You don't need to regenerate embeddings." . PHP_EOL;
                echo "" . PHP_EOL;
                echo "Sample response:" . PHP_EOL;
                echo substr($result['response'], 0, 300) . "..." . PHP_EOL;
                
                exit(0);
            } else {
                echo "⚠️  Response seems generic (no specific bill references)" . PHP_EOL;
                echo "First 200 chars: " . substr($result['response'], 0, 200) . "..." . PHP_EOL;
            }
        } else {
            echo "❌ Chatbot failed: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
        }
        
    } catch (Exception $e) {
        echo "❌ Error testing: " . $e->getMessage() . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    echo "Recommendation: Your embeddings might need regeneration or the search parameters need tuning." . PHP_EOL;
    
} else {
    echo "❌ No embeddings found. You definitely need to generate them." . PHP_EOL;
}

echo "" . PHP_EOL;
echo "Next steps:" . PHP_EOL;
echo "1. If chatbot is working: You're done! ✅" . PHP_EOL;
echo "2. If not working: Run 'php embed-with-detailed-progress.php'" . PHP_EOL;