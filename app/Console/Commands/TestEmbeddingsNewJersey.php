<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CongressChatbotService;
use App\Services\SemanticSearchService;

class TestEmbeddingsNewJersey extends Command
{
    protected $signature = 'test:embeddings-nj';
    protected $description = 'Test New Jersey queries with embeddings';

    public function handle(CongressChatbotService $chatbotService, SemanticSearchService $semanticSearchService)
    {
        $this->info('🧪 Testing New Jersey queries with embeddings...');
        
        // First check if we have embeddings
        $embeddingCount = \DB::table('embeddings')->count();
        $this->info("📊 Found {$embeddingCount} embeddings in database");
        
        if ($embeddingCount === 0) {
            $this->warn('⚠️  No embeddings found. Run: php artisan embeddings:generate');
            $this->info('For now, testing with regular database queries...');
        }
        
        $questions = [
            "What bills come out of New Jersey?",
            "Show me legislation from NJ representatives",
            "What are New Jersey members working on?",
            "Bills sponsored by New Jersey politicians",
            "New Jersey congressional activity"
        ];
        
        foreach ($questions as $question) {
            $this->info("\n" . str_repeat('=', 70));
            $this->info("🔍 QUESTION: {$question}");
            $this->info(str_repeat('=', 70));
            
            $startTime = microtime(true);
            
            // Test chatbot response
            try {
                $result = $chatbotService->askQuestion($question);
            } catch (\Exception $e) {
                $result = [
                    'success' => false,
                    'error' => 'Failed to process question: ' . $e->getMessage()
                ];
            }
            
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            if ($result['success']) {
                $this->info("✅ Response generated in {$duration}ms");
                
                // Show method used
                $method = $result['method'] ?? 'fallback';
                $this->info("🔧 Method: {$method}");
                
                // Show data sources
                if (!empty($result['data_sources'])) {
                    $this->info("📊 Data Sources:");
                    foreach ($result['data_sources'] as $source) {
                        $this->line("   • {$source}");
                    }
                }
                
                // Show semantic results count if available
                if (isset($result['semantic_results_count'])) {
                    $this->info("🎯 Semantic matches: {$result['semantic_results_count']}");
                }
                
                // Show response (truncated)
                $this->info("\n📝 Response:");
                $response = $result['response'];
                if (strlen($response) > 500) {
                    $response = substr($response, 0, 500) . '...';
                }
                $this->line($response);
                
            } else {
                $this->error("❌ Query failed: {$result['error']}");
            }
            
            // If we have embeddings, also test direct semantic search
            if ($embeddingCount > 0) {
                $this->info("\n🔍 Direct semantic search test:");
                
                $semanticResult = $semanticSearchService->search($question, [
                    'limit' => 5,
                    'threshold' => 0.6
                ]);
                
                if ($semanticResult['success']) {
                    $this->info("✅ Found {$semanticResult['total_found']} semantic matches");
                    
                    foreach (array_slice($semanticResult['results'], 0, 3) as $match) {
                        $similarity = round($match['similarity'] * 100, 1);
                        $type = ucfirst($match['entity_type']);
                        
                        $this->line("   • {$type} ({$similarity}% match)");
                        
                        if (isset($match['model'])) {
                            if ($match['entity_type'] === 'bill') {
                                $bill = $match['model'];
                                $this->line("     {$bill->congress_id}: " . substr($bill->title, 0, 60) . "...");
                            } elseif ($match['entity_type'] === 'member') {
                                $member = $match['model'];
                                $this->line("     {$member->display_name} ({$member->party_abbreviation}-{$member->state})");
                            }
                        }
                    }
                } else {
                    $this->error("❌ Semantic search failed: {$semanticResult['error']}");
                }
            }
            
            $this->info("\n");
        }
        
        // Summary
        $this->info(str_repeat('=', 70));
        $this->info('📊 SUMMARY');
        $this->info(str_repeat('=', 70));
        
        if ($embeddingCount > 0) {
            $this->info("✅ Embeddings system is active with {$embeddingCount} embeddings");
            $this->info("🎯 Semantic search should provide more relevant results");
        } else {
            $this->warn("⚠️  Embeddings not generated yet");
            $this->info("💡 To enable semantic search, run:");
            $this->line("   php artisan migrate");
            $this->line("   php artisan embeddings:generate");
        }
        
        return 0;
    }
}