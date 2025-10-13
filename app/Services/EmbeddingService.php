<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EmbeddingService
{
    private ?string $apiKey;
    private string $baseUrl = 'https://api.voyageai.com/v1';
    
    public function __construct()
    {
        $this->apiKey = config('services.voyage.api_key');
    }

    /**
     * Generate embeddings for text using Voyage AI's voyage-3-large model
     */
    public function generateEmbedding(string $text): ?array
    {
        if (!$this->apiKey) {
            Log::warning('Voyage AI API key not configured for embeddings');
            return null;
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/embeddings', [
                'model' => 'voyage-3-large',
                'input' => $text,
                'input_type' => 'document'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'][0]['embedding'] ?? null;
            }

            Log::error('Voyage AI embedding API error', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Embedding generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ]);
            return null;
        }
    }

    /**
     * Generate embeddings for multiple texts in batch
     */
    public function generateBatchEmbeddings(array $texts): array
    {
        if (!$this->apiKey) {
            Log::warning('Voyage AI API key not configured for batch embeddings');
            return array_fill(0, count($texts), null);
        }
        
        $embeddings = [];
        
        // Process in chunks - Voyage supports up to 128 texts per request
        $chunks = array_chunk($texts, 128);
        
        foreach ($chunks as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])->post($this->baseUrl . '/embeddings', [
                    'model' => 'voyage-3-large',
                    'input' => $chunk,
                    'input_type' => 'document'
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    Log::info('Batch embedding successful', [
                        'chunk_size' => count($chunk),
                        'response_data_count' => count($data['data'] ?? [])
                    ]);
                    foreach ($data['data'] as $item) {
                        $embeddings[] = $item['embedding'];
                    }
                } else {
                    Log::error('Batch embedding API error', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                        'chunk_size' => count($chunk)
                    ]);
                    // Fill with nulls for failed batch
                    $embeddings = array_merge($embeddings, array_fill(0, count($chunk), null));
                }
            } catch (\Exception $e) {
                Log::error('Batch embedding generation failed', [
                    'error' => $e->getMessage(),
                    'chunk_size' => count($chunk)
                ]);
                $embeddings = array_merge($embeddings, array_fill(0, count($chunk), null));
            }
            
            // Rate limiting - wait between batches
            usleep(100000); // 100ms delay
        }
        
        return $embeddings;
    }

    /**
     * Calculate cosine similarity between two embeddings
     */
    public function cosineSimilarity(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $norm1 += $embedding1[$i] * $embedding1[$i];
            $norm2 += $embedding2[$i] * $embedding2[$i];
        }

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Store embedding in database
     */
    public function storeEmbedding(string $entityType, int $entityId, array $embedding, string $content, array $metadata = []): bool
    {
        try {
            DB::table('embeddings')->updateOrInsert(
                [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId
                ],
                [
                    'embedding' => json_encode($embedding),
                    'content' => $content,
                    'metadata' => json_encode($metadata),
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store embedding', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Search for similar embeddings
     */
    public function searchSimilar(array $queryEmbedding, string $entityType = null, int $limit = 10, float $threshold = 0.7): array
    {
        $query = DB::table('embeddings');
        
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        
        $embeddings = $query->get();
        $results = [];
        
        foreach ($embeddings as $embedding) {
            $storedEmbedding = json_decode($embedding->embedding, true);
            $similarity = $this->cosineSimilarity($queryEmbedding, $storedEmbedding);
            
            if ($similarity >= $threshold) {
                $results[] = [
                    'entity_type' => $embedding->entity_type,
                    'entity_id' => $embedding->entity_id,
                    'similarity' => $similarity,
                    'content' => $embedding->content,
                    'metadata' => json_decode($embedding->metadata, true)
                ];
            }
        }
        
        // Sort by similarity descending
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $limit);
    }
}