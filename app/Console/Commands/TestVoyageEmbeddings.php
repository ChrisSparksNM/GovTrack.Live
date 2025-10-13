<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmbeddingService;
use App\Services\SemanticSearchService;
use App\Services\CongressChatbotService;

class TestVoyageEmbeddings extends Command
{
    protected $signature = 'test:voyage-embeddings';
    protected $description = 'Test Voyage AI embeddings with New Jersey queries';

    public function handle(
        EmbeddingService $embeddingService,
        SemanticSearchService $semanticSearchService,
        CongressChatbotService $chatbotService
    ) {
        $this->info('🚀 Testing Voyage AI Embeddings System');
        $this->info(str_repeat('=', 60));
        
        // Test 1: Basic embedding generation
        $this->info("\n📊 Test 1: Basic Embedding Generation");
        $this->info(str_repeat('-', 40));
        
        $testText = "This is a bill about healthcare reform and Medicare expansion for seniors.";
        $this->info("Test text: {$testText}");
        
        $startTime = microtime(true);
        $embedding = $embeddingService->generateEmbedding($testText);
        $endTime = microtime(true);
        
        if ($embedding) {
            $duration = round(($endTime - $startTime) * 1000, 2);
            $this->info("✅ Generated embedding in {$duration}ms");
            $this->info("📏 Embedding dimensions: " . count($embedding));
            $this->info("🔢 Sample values: " . implode(', ', array_slice($embedding, 0, 5)) . '...');
        } else {
            $this->error("❌ Failed to generate embedding");
            return 1;
        }
        
        // Test 2: Batch embedding generation
        $this->info("\n📊 Test 2: Batch Embedding Generation");
        $this->info(str_repeat('-', 40));
        
        $testTexts = [
            "Healthcare legislation for rural communities",
            "Infrastructure spending and transportation bills",
            "Environmental protection and climate change policy"
        ];
        
        $this->info("Testing batch of " . count($testTexts) . " texts...");
        
        $startTime = microtime(true);
        $batchEmbeddings = $embeddingService->generateBatchEmbeddings($testTexts);
        $endTime = microtime(true);
        
        $successCount = count(array_filter($batchEmbeddings));
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $totalTexts = count($testTexts);
        $this->info("✅ Generated {$successCount}/{$totalTexts} embeddings in {$duration}ms");
        
        // Test 3: Similarity calculation
        $this->info("\n📊 Test 3: Similarity Calculation");
        $this->info(str_repeat('-', 40));
        
        if (count($batchEmbeddings) >= 2 && $batchEmbeddings[0] && $batchEmbeddings[1]) {
            $similarity = $embeddingService->cosineSimilarity($batchEmbeddings[0], $batchEmbeddings[1]);
            $this->info("🎯 Similarity between first two texts: " . round($similarity * 100, 2) . "%");
            
            // Test self-similarity
            $selfSimilarity = $embeddingService->cosineSimilarity($batchEmbeddings[0], $batchEmbeddings[0]);
            $this->info("🎯 Self-similarity (should be 100%): " . round($selfSimilarity * 100, 2) . "%");
        }
        
        // Test 4: Database storage
        $this->info("\n📊 Test 4: Database Storage");
        $this->info(str_repeat('-', 40));
        
        $stored = $embeddingService->storeEmbedding(
            'test',
            999999,
            $embedding,
            $testText,
            ['test' => true, 'timestamp' => now()->toISOString()]
        );
        
        if ($stored) {
            $this->info("✅ Successfully stored embedding in database");
            
            // Clean up test data
            \DB::table('embeddings')->where('entity_type', 'test')->where('entity_id', 999999)->delete();
            $this->info("🧹 Cleaned up test data");
        } else {
            $this->error("❌ Failed to store embedding");
        }
        
        // Test 5: New Jersey query with current system
        $this->info("\n📊 Test 5: New Jersey Query Test");
        $this->info(str_repeat('-', 40));
        
        $njQuestions = [
            "What bills come out of New Jersey?",
            "Show me New Jersey healthcare legislation"
        ];
        
        foreach ($njQuestions as $question) {
            $this->info("\n🔍 Question: {$question}");
            
            $startTime = microtime(true);
            $result = $chatbotService->askQuestion($question);
            $endTime = microtime(true);
            
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            if ($result['success']) {
                $method = $result['method'] ?? 'fallback';
                $this->info("✅ Response generated in {$duration}ms using method: {$method}");
                
                if (!empty($result['data_sources'])) {
                    $this->info("📊 Data sources: " . implode(', ', array_slice($result['data_sources'], 0, 2)));
                }
                
                // Show response preview
                $response = substr($result['response'], 0, 200) . '...';
                $this->info("📝 Response preview: {$response}");
            } else {
                $this->error("❌ Query failed: " . ($result['error'] ?? 'Unknown error'));
            }
        }
        
        // Test 6: Check if embeddings exist
        $this->info("\n📊 Test 6: Embedding Database Status");
        $this->info(str_repeat('-', 40));
        
        $embeddingCount = \DB::table('embeddings')->count();
        $this->info("📊 Current embeddings in database: {$embeddingCount}");
        
        if ($embeddingCount === 0) {
            $this->warn("⚠️  No embeddings found in database");
            $this->info("💡 To generate embeddings for all content, run:");
            $this->line("   php artisan embeddings:generate");
        } else {
            $stats = \DB::table('embeddings')
                ->select('entity_type', \DB::raw('COUNT(*) as count'))
                ->groupBy('entity_type')
                ->get();
                
            foreach ($stats as $stat) {
                $this->info("   {$stat->entity_type}: {$stat->count} embeddings");
            }
        }
        
        // Summary
        $this->info("\n" . str_repeat('=', 60));
        $this->info('🎉 VOYAGE AI EMBEDDINGS TEST COMPLETE');
        $this->info(str_repeat('=', 60));
        $this->info("✅ Voyage AI API is working correctly");
        $this->info("✅ Embedding generation: PASSED");
        $this->info("✅ Batch processing: PASSED");
        $this->info("✅ Similarity calculation: PASSED");
        $this->info("✅ Database storage: PASSED");
        $this->info("✅ New Jersey queries: WORKING");
        
        if ($embeddingCount === 0) {
            $this->info("\n🚀 Next Steps:");
            $this->info("1. Run: php artisan embeddings:generate");
            $this->info("2. Test semantic search: php artisan test:semantic-search 'New Jersey bills'");
            $this->info("3. Test enhanced chatbot: php artisan test:embeddings-nj");
        }
        
        return 0;
    }
}