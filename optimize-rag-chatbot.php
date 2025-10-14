<?php

echo "=== RAG Chatbot Optimization Guide ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Your chatbot already implements RAG (Retrieval-Augmented Generation)!" . PHP_EOL;
echo "Here's how to optimize it:" . PHP_EOL;
echo "" . PHP_EOL;

// Check current RAG components
echo "1. Checking RAG Components:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

// Check embeddings (Knowledge Base)
$embeddingCount = DB::table('embeddings')->count();
echo "📚 Knowledge Base (Embeddings): " . ($embeddingCount > 0 ? "✅ $embeddingCount embeddings" : "❌ No embeddings found") . PHP_EOL;

// Check data sources
$billCount = DB::table('bills')->count();
$memberCount = DB::table('members')->count();
$actionCount = DB::table('bill_actions')->count();

echo "📊 Data Sources:" . PHP_EOL;
echo "   - Bills: $billCount" . PHP_EOL;
echo "   - Members: $memberCount" . PHP_EOL;
echo "   - Actions: $actionCount" . PHP_EOL;

// Check AI service
$anthropicKey = env('ANTHROPIC_API_KEY');
echo "🤖 AI Service: " . ($anthropicKey ? "✅ Anthropic configured" : "❌ No Anthropic key") . PHP_EOL;

// Check embedding service
$voyageKey = env('VOYAGE_API_KEY');
echo "🔍 Embedding Service: " . ($voyageKey ? "✅ Voyage AI configured" : "❌ No Voyage key") . PHP_EOL;

echo "" . PHP_EOL;

if ($embeddingCount == 0) {
    echo "🚨 CRITICAL: Your RAG system needs embeddings to work!" . PHP_EOL;
    echo "Run: php generate-embeddings-comprehensive.php" . PHP_EOL;
    echo "" . PHP_EOL;
}

echo "2. RAG Pipeline Flow:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;
echo "User Question → Embedding → Semantic Search → Context Retrieval → AI Generation → Response" . PHP_EOL;
echo "" . PHP_EOL;

echo "3. Testing RAG Components:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

try {
    // Test semantic search
    if ($embeddingCount > 0) {
        echo "Testing semantic search..." . PHP_EOL;
        
        $semanticService = app('App\Services\SemanticSearchService');
        $results = $semanticService->searchBills('healthcare', ['limit' => 3]);
        
        if ($results['success'] && count($results['bills']) > 0) {
            echo "✅ Semantic search working - found " . count($results['bills']) . " healthcare bills" . PHP_EOL;
            
            foreach ($results['bills'] as $i => $result) {
                $bill = $result['model'];
                echo "   " . ($i + 1) . ". " . substr($bill->title, 0, 60) . "..." . PHP_EOL;
            }
        } else {
            echo "❌ Semantic search not finding results" . PHP_EOL;
        }
    } else {
        echo "⏭️  Skipping semantic search test (no embeddings)" . PHP_EOL;
    }
    
    echo "" . PHP_EOL;
    
    // Test full RAG pipeline
    echo "Testing full RAG pipeline..." . PHP_EOL;
    
    $chatbotService = app('App\Services\CongressChatbotService');
    $result = $chatbotService->askQuestion('What healthcare bills exist?');
    
    if ($result['success']) {
        echo "✅ RAG pipeline working!" . PHP_EOL;
        echo "Method used: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        echo "Response length: " . strlen($result['response']) . " chars" . PHP_EOL;
        
        if (isset($result['data_sources'])) {
            echo "Data sources: " . count($result['data_sources']) . PHP_EOL;
        }
        
        // Check if response contains specific data
        if (strpos($result['response'], 'HR ') !== false || strpos($result['response'], 'S ') !== false) {
            echo "✅ Response contains specific bill references" . PHP_EOL;
        } else {
            echo "⚠️  Response seems generic (no specific bill references)" . PHP_EOL;
        }
    } else {
        echo "❌ RAG pipeline failed: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error testing RAG: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "4. RAG Optimization Recommendations:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;

if ($embeddingCount == 0) {
    echo "🔥 PRIORITY 1: Generate embeddings" . PHP_EOL;
    echo "   Command: php generate-embeddings-comprehensive.php" . PHP_EOL;
    echo "" . PHP_EOL;
}

echo "🎯 Optimization Tips:" . PHP_EOL;
echo "   1. Increase embedding batch size for faster processing" . PHP_EOL;
echo "   2. Tune similarity thresholds in SemanticSearchService" . PHP_EOL;
echo "   3. Add more context fields to embeddings" . PHP_EOL;
echo "   4. Implement hybrid search (semantic + keyword)" . PHP_EOL;
echo "   5. Cache frequent queries for better performance" . PHP_EOL;

echo "" . PHP_EOL;
echo "5. Advanced RAG Features to Add:" . PHP_EOL;
echo str_repeat("-", 40) . PHP_EOL;
echo "   • Multi-step reasoning (chain of thought)" . PHP_EOL;
echo "   • Cross-reference validation" . PHP_EOL;
echo "   • Temporal awareness (recent vs historical)" . PHP_EOL;
echo "   • Confidence scoring" . PHP_EOL;
echo "   • Source citation improvements" . PHP_EOL;

echo "" . PHP_EOL;
echo "🚀 Your RAG system is already built - it just needs embeddings to work!" . PHP_EOL;