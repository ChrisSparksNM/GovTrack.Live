<?php

namespace App\Console\Commands;

use App\Services\EmbeddingService;
use Illuminate\Console\Command;

class TestEmbeddingService extends Command
{
    protected $signature = 'test:embeddings {--text=Test embedding generation}';
    protected $description = 'Test the embedding service with detailed error reporting';

    public function handle(EmbeddingService $embeddingService)
    {
        $text = $this->option('text');
        
        $this->info("🧪 Testing Embedding Service");
        $this->info("Text: {$text}");
        $this->line(str_repeat('-', 50));
        
        // Check API key configuration
        $apiKey = config('services.voyage.api_key');
        if (!$apiKey) {
            $this->error('❌ VOYAGE_API_KEY not configured');
            $this->line('Set VOYAGE_API_KEY in your environment variables');
            return 1;
        }
        
        $this->info('✅ API key configured: ' . substr($apiKey, 0, 8) . '...');
        
        // Test embedding generation
        $this->info('🔄 Generating embedding...');
        
        try {
            $embedding = $embeddingService->generateEmbedding($text);
            
            if ($embedding) {
                $this->info('✅ Embedding generated successfully!');
                $this->line('   Dimensions: ' . count($embedding));
                $this->line('   First 5 values: ' . implode(', ', array_slice($embedding, 0, 5)));
                
                // Test similarity calculation
                $embedding2 = $embeddingService->generateEmbedding($text . ' modified');
                if ($embedding2) {
                    $similarity = $this->cosineSimilarity($embedding, $embedding2);
                    $this->line('   Similarity with modified text: ' . round($similarity * 100, 2) . '%');
                }
                
                return 0;
            } else {
                $this->error('❌ Failed to generate embedding');
                $this->line('Check the Laravel logs for more details: tail -f storage/logs/laravel.log');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Exception: ' . $e->getMessage());
            $this->line('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
    
    private function cosineSimilarity(array $a, array $b): float
    {
        $dotProduct = 0;
        $normA = 0;
        $normB = 0;
        
        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }
        
        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }
}