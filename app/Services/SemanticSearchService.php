<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Member;
use App\Models\BillAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SemanticSearchService
{
    private EmbeddingService $embeddingService;
    
    public function __construct(EmbeddingService $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Perform semantic search across all embedded content
     */
    public function search(string $query, array $options = []): array
    {
        $entityTypes = $options['entity_types'] ?? ['bill', 'member', 'bill_action'];
        $limit = $options['limit'] ?? 20;
        $threshold = $options['threshold'] ?? 0.7;
        $includeMetadata = $options['include_metadata'] ?? true;
        
        // Generate embedding for the query
        $queryEmbedding = $this->embeddingService->generateEmbedding($query);
        
        if (!$queryEmbedding) {
            return [
                'success' => false,
                'error' => 'Failed to generate query embedding'
            ];
        }
        
        $allResults = [];
        
        // Search each entity type
        foreach ($entityTypes as $entityType) {
            $results = $this->embeddingService->searchSimilar(
                $queryEmbedding, 
                $entityType, 
                $limit, 
                $threshold
            );
            
            // Enrich results with actual model data
            $enrichedResults = $this->enrichResults($results, $entityType, $includeMetadata);
            $allResults = array_merge($allResults, $enrichedResults);
        }
        
        // Sort all results by similarity
        usort($allResults, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        return [
            'success' => true,
            'query' => $query,
            'results' => array_slice($allResults, 0, $limit),
            'total_found' => count($allResults)
        ];
    }

    /**
     * Search specifically for bills with semantic matching
     */
    public function searchBills(string $query, array $filters = []): array
    {
        $options = [
            'entity_types' => ['bill'],
            'limit' => $filters['limit'] ?? 15,
            'threshold' => $filters['threshold'] ?? 0.6,
            'include_metadata' => true
        ];
        
        $searchResults = $this->search($query, $options);
        
        if (!$searchResults['success']) {
            return $searchResults;
        }
        
        // Apply additional filters
        $filteredResults = $this->applyBillFilters($searchResults['results'], $filters);
        
        return [
            'success' => true,
            'query' => $query,
            'bills' => $filteredResults,
            'total_found' => count($filteredResults)
        ];
    }

    /**
     * Search for members with semantic matching
     */
    public function searchMembers(string $query, array $filters = []): array
    {
        $options = [
            'entity_types' => ['member'],
            'limit' => $filters['limit'] ?? 15,
            'threshold' => $filters['threshold'] ?? 0.6,
            'include_metadata' => true
        ];
        
        $searchResults = $this->search($query, $options);
        
        if (!$searchResults['success']) {
            return $searchResults;
        }
        
        // Apply additional filters
        $filteredResults = $this->applyMemberFilters($searchResults['results'], $filters);
        
        return [
            'success' => true,
            'query' => $query,
            'members' => $filteredResults,
            'total_found' => count($filteredResults)
        ];
    }

    /**
     * Find related content based on a specific entity
     */
    public function findRelated(string $entityType, int $entityId, int $limit = 10): array
    {
        // Get the embedding for the source entity
        $sourceEmbedding = DB::table('embeddings')
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();
            
        if (!$sourceEmbedding) {
            return [
                'success' => false,
                'error' => 'Source entity embedding not found'
            ];
        }
        
        $embedding = json_decode($sourceEmbedding->embedding, true);
        
        // Find similar content (excluding the source)
        $results = $this->embeddingService->searchSimilar($embedding, null, $limit + 1, 0.5);
        
        // Remove the source entity from results
        $results = array_filter($results, function($result) use ($entityType, $entityId) {
            return !($result['entity_type'] === $entityType && $result['entity_id'] === $entityId);
        });
        
        // Re-index and limit
        $results = array_slice(array_values($results), 0, $limit);
        
        // Enrich with model data
        $enrichedResults = [];
        foreach ($results as $result) {
            $enriched = $this->enrichResults([$result], $result['entity_type'], true);
            $enrichedResults = array_merge($enrichedResults, $enriched);
        }
        
        return [
            'success' => true,
            'source' => [
                'entity_type' => $entityType,
                'entity_id' => $entityId
            ],
            'related' => $enrichedResults
        ];
    }

    /**
     * Enrich search results with actual model data
     */
    private function enrichResults(array $results, string $entityType, bool $includeMetadata): array
    {
        if (empty($results)) {
            return [];
        }
        
        $entityIds = array_column($results, 'entity_id');
        $models = [];
        
        switch ($entityType) {
            case 'bill':
                $models = Bill::whereIn('id', $entityIds)
                    ->with(['sponsors', 'cosponsors'])
                    ->get()
                    ->keyBy('id');
                break;
                
            case 'member':
                $models = Member::whereIn('id', $entityIds)
                    ->get()
                    ->keyBy('id');
                break;
                
            case 'bill_action':
                $models = BillAction::whereIn('id', $entityIds)
                    ->with('bill')
                    ->get()
                    ->keyBy('id');
                break;
        }
        
        $enrichedResults = [];
        
        foreach ($results as $result) {
            $model = $models->get($result['entity_id']);
            
            if ($model) {
                $enriched = [
                    'entity_type' => $result['entity_type'],
                    'entity_id' => $result['entity_id'],
                    'similarity' => $result['similarity'],
                    'content' => $result['content'],
                    'model' => $model
                ];
                
                if ($includeMetadata) {
                    $enriched['metadata'] = $result['metadata'];
                }
                
                $enrichedResults[] = $enriched;
            }
        }
        
        return $enrichedResults;
    }

    /**
     * Apply additional filters to bill search results
     */
    private function applyBillFilters(array $results, array $filters): array
    {
        if (empty($filters)) {
            return $results;
        }
        
        return array_filter($results, function($result) use ($filters) {
            if ($result['entity_type'] !== 'bill') {
                return true;
            }
            
            $metadata = $result['metadata'] ?? [];
            
            // Filter by congress
            if (isset($filters['congress']) && $metadata['congress'] != $filters['congress']) {
                return false;
            }
            
            // Filter by chamber
            if (isset($filters['chamber']) && $metadata['origin_chamber'] !== $filters['chamber']) {
                return false;
            }
            
            // Filter by state (sponsor states)
            if (isset($filters['state'])) {
                $sponsorStates = $metadata['sponsor_states'] ?? [];
                if (!in_array($filters['state'], $sponsorStates)) {
                    return false;
                }
            }
            
            // Filter by party (sponsor parties)
            if (isset($filters['party'])) {
                $sponsorParties = $metadata['sponsor_parties'] ?? [];
                if (!in_array($filters['party'], $sponsorParties)) {
                    return false;
                }
            }
            
            return true;
        });
    }

    /**
     * Apply additional filters to member search results
     */
    private function applyMemberFilters(array $results, array $filters): array
    {
        if (empty($filters)) {
            return $results;
        }
        
        return array_filter($results, function($result) use ($filters) {
            if ($result['entity_type'] !== 'member') {
                return true;
            }
            
            $metadata = $result['metadata'] ?? [];
            
            // Filter by state
            if (isset($filters['state']) && $metadata['state'] !== $filters['state']) {
                return false;
            }
            
            // Filter by party
            if (isset($filters['party']) && $metadata['party_abbreviation'] !== $filters['party']) {
                return false;
            }
            
            // Filter by chamber
            if (isset($filters['chamber']) && $metadata['chamber'] !== $filters['chamber']) {
                return false;
            }
            
            // Filter by current members only
            if (isset($filters['current_only']) && $filters['current_only'] && !$metadata['current_member']) {
                return false;
            }
            
            return true;
        });
    }
}