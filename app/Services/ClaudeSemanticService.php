<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClaudeSemanticService
{
    private AnthropicService $anthropicService;
    
    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
    }

    /**
     * Generate semantic fingerprint using Claude's analysis
     */
    public function generateSemanticFingerprint(string $text): ?array
    {
        try {
            $prompt = "Analyze the following text and extract semantic features. Return ONLY a JSON object with these exact keys:

{
  \"topics\": [\"topic1\", \"topic2\", \"topic3\"],
  \"entities\": [\"entity1\", \"entity2\"],
  \"sentiment\": \"positive|negative|neutral\",
  \"policy_areas\": [\"area1\", \"area2\"],
  \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\"],
  \"themes\": [\"theme1\", \"theme2\"],
  \"urgency\": \"high|medium|low\",
  \"scope\": \"local|state|national|international\"
}

Text to analyze:
{$text}

Return only the JSON object, no other text:";

            $response = $this->anthropicService->generateChatResponse($prompt);
            
            if ($response['success']) {
                $jsonStr = trim($response['response']);
                
                // Try to extract JSON from response
                if (preg_match('/\{[\s\S]*\}/', $jsonStr, $matches)) {
                    $jsonStr = $matches[0];
                }
                
                $fingerprint = json_decode($jsonStr, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $fingerprint;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Claude semantic fingerprint generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ]);
            return null;
        }
    }

    /**
     * Calculate semantic similarity between two fingerprints
     */
    public function calculateSimilarity(array $fingerprint1, array $fingerprint2): float
    {
        $totalScore = 0.0;
        $weights = [
            'topics' => 0.25,
            'entities' => 0.20,
            'policy_areas' => 0.20,
            'keywords' => 0.15,
            'themes' => 0.10,
            'sentiment' => 0.05,
            'urgency' => 0.03,
            'scope' => 0.02
        ];
        
        foreach ($weights as $field => $weight) {
            $score = $this->calculateFieldSimilarity($fingerprint1[$field] ?? [], $fingerprint2[$field] ?? [], $field);
            $totalScore += $score * $weight;
        }
        
        return min(1.0, $totalScore);
    }

    /**
     * Calculate similarity for a specific field
     */
    private function calculateFieldSimilarity($value1, $value2, string $field): float
    {
        if (in_array($field, ['sentiment', 'urgency', 'scope'])) {
            // Exact match for categorical fields
            return $value1 === $value2 ? 1.0 : 0.0;
        }
        
        if (is_array($value1) && is_array($value2)) {
            // Jaccard similarity for array fields
            $intersection = array_intersect($value1, $value2);
            $union = array_unique(array_merge($value1, $value2));
            
            return count($union) > 0 ? count($intersection) / count($union) : 0.0;
        }
        
        return 0.0;
    }

    /**
     * Store semantic fingerprint in database
     */
    public function storeFingerprint(string $entityType, int $entityId, array $fingerprint, string $content, array $metadata = []): bool
    {
        try {
            DB::table('semantic_fingerprints')->updateOrInsert(
                [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId
                ],
                [
                    'fingerprint' => json_encode($fingerprint),
                    'content' => $content,
                    'metadata' => json_encode($metadata),
                    'updated_at' => now(),
                    'created_at' => now()
                ]
            );
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store semantic fingerprint', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Search for semantically similar content
     */
    public function searchSimilar(array $queryFingerprint, string $entityType = null, int $limit = 10, float $threshold = 0.3): array
    {
        $query = DB::table('semantic_fingerprints');
        
        if ($entityType) {
            $query->where('entity_type', $entityType);
        }
        
        $fingerprints = $query->get();
        $results = [];
        
        foreach ($fingerprints as $stored) {
            $storedFingerprint = json_decode($stored->fingerprint, true);
            $similarity = $this->calculateSimilarity($queryFingerprint, $storedFingerprint);
            
            if ($similarity >= $threshold) {
                $results[] = [
                    'entity_type' => $stored->entity_type,
                    'entity_id' => $stored->entity_id,
                    'similarity' => $similarity,
                    'content' => $stored->content,
                    'metadata' => json_decode($stored->metadata, true),
                    'fingerprint' => $storedFingerprint
                ];
            }
        }
        
        // Sort by similarity descending
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return array_slice($results, 0, $limit);
    }

    /**
     * Perform semantic search using Claude analysis
     */
    public function semanticSearch(string $query, array $options = []): array
    {
        $entityTypes = $options['entity_types'] ?? ['bill', 'member', 'bill_action'];
        $limit = $options['limit'] ?? 20;
        $threshold = $options['threshold'] ?? 0.3;
        
        // Generate fingerprint for the query
        $queryFingerprint = $this->generateSemanticFingerprint($query);
        
        if (!$queryFingerprint) {
            return [
                'success' => false,
                'error' => 'Failed to generate semantic fingerprint for query'
            ];
        }
        
        $allResults = [];
        
        // Search each entity type
        foreach ($entityTypes as $entityType) {
            $results = $this->searchSimilar($queryFingerprint, $entityType, $limit, $threshold);
            $allResults = array_merge($allResults, $results);
        }
        
        // Sort all results by similarity
        usort($allResults, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return [
            'success' => true,
            'query' => $query,
            'query_fingerprint' => $queryFingerprint,
            'results' => array_slice($allResults, 0, $limit),
            'total_found' => count($allResults)
        ];
    }

    /**
     * Enhanced search that combines Claude analysis with keyword matching
     */
    public function hybridSearch(string $query, array $options = []): array
    {
        // First, try semantic search
        $semanticResults = $this->semanticSearch($query, $options);
        
        if (!$semanticResults['success']) {
            return $semanticResults;
        }
        
        // Enhance with Claude's direct analysis of the query
        $enhancedAnalysis = $this->analyzeQueryIntent($query);
        
        // Filter and re-rank results based on enhanced analysis
        if ($enhancedAnalysis) {
            $semanticResults['results'] = $this->reRankResults(
                $semanticResults['results'], 
                $enhancedAnalysis
            );
            $semanticResults['query_analysis'] = $enhancedAnalysis;
        }
        
        return $semanticResults;
    }

    /**
     * Analyze query intent using Claude
     */
    private function analyzeQueryIntent(string $query): ?array
    {
        $prompt = "Analyze this search query and return ONLY a JSON object with query intent:

{
  \"intent_type\": \"find_bills|find_members|find_actions|general_info\",
  \"focus_areas\": [\"area1\", \"area2\"],
  \"geographic_scope\": \"state_name or null\",
  \"time_scope\": \"recent|historical|current|null\",
  \"priority_keywords\": [\"keyword1\", \"keyword2\"],
  \"expected_entity_types\": [\"bill\", \"member\", \"action\"]
}

Query: {$query}

Return only the JSON:";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        if ($response['success']) {
            $jsonStr = trim($response['response']);
            if (preg_match('/\{[\s\S]*\}/', $jsonStr, $matches)) {
                $analysis = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $analysis;
                }
            }
        }
        
        return null;
    }

    /**
     * Re-rank results based on query analysis
     */
    private function reRankResults(array $results, array $queryAnalysis): array
    {
        foreach ($results as &$result) {
            $boost = 0.0;
            
            // Boost based on entity type match
            if (in_array($result['entity_type'], $queryAnalysis['expected_entity_types'] ?? [])) {
                $boost += 0.1;
            }
            
            // Boost based on geographic relevance
            if (!empty($queryAnalysis['geographic_scope'])) {
                $metadata = $result['metadata'] ?? [];
                if (isset($metadata['state']) && 
                    strtolower($metadata['state']) === strtolower($queryAnalysis['geographic_scope'])) {
                    $boost += 0.2;
                }
            }
            
            // Boost based on keyword presence in content
            foreach ($queryAnalysis['priority_keywords'] ?? [] as $keyword) {
                if (stripos($result['content'], $keyword) !== false) {
                    $boost += 0.05;
                }
            }
            
            $result['similarity'] = min(1.0, $result['similarity'] + $boost);
        }
        
        // Re-sort by updated similarity
        usort($results, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return $results;
    }
}