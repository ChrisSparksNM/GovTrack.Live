<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EnhancedRAGService
{
    private SemanticSearchService $semanticSearch;
    private AnthropicService $anthropicService;
    
    public function __construct(
        SemanticSearchService $semanticSearch,
        AnthropicService $anthropicService
    ) {
        $this->semanticSearch = $semanticSearch;
        $this->anthropicService = $anthropicService;
    }

    /**
     * Enhanced RAG pipeline with multi-step retrieval
     */
    public function askQuestion(string $question, array $options = []): array
    {
        $startTime = microtime(true);
        
        try {
            // Step 1: Analyze question intent
            $intent = $this->analyzeQuestionIntent($question);
            
            // Step 2: Multi-modal retrieval
            $retrievalResults = $this->performMultiModalRetrieval($question, $intent);
            
            // Step 3: Context synthesis
            $context = $this->synthesizeContext($retrievalResults, $intent);
            
            // Step 4: Generate response with enhanced prompting
            $response = $this->generateEnhancedResponse($question, $context, $intent);
            
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'response' => $response['content'],
                'response_html' => $response['html'],
                'method' => 'enhanced_rag',
                'context_sources' => $retrievalResults['sources'],
                'confidence_score' => $this->calculateConfidenceScore($retrievalResults),
                'processing_time_ms' => $processingTime,
                'retrieval_stats' => $retrievalResults['stats'],
                'intent_analysis' => $intent
            ];
            
        } catch (\Exception $e) {
            Log::error('Enhanced RAG error', [
                'question' => $question,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to process question: ' . $e->getMessage(),
                'method' => 'enhanced_rag_error'
            ];
        }
    }

    /**
     * Analyze question to determine intent and required data types
     */
    private function analyzeQuestionIntent(string $question): array
    {
        $question = strtolower($question);
        
        $intent = [
            'primary_focus' => 'general',
            'data_types' => [],
            'temporal_scope' => 'current',
            'analysis_type' => 'informational',
            'specificity' => 'general'
        ];
        
        // Determine primary focus
        if (preg_match('/\b(bill|legislation|law|act)\b/', $question)) {
            $intent['primary_focus'] = 'bills';
            $intent['data_types'][] = 'bill';
        }
        
        if (preg_match('/\b(member|representative|senator|congress|sponsor)\b/', $question)) {
            $intent['primary_focus'] = 'members';
            $intent['data_types'][] = 'member';
        }
        
        if (preg_match('/\b(action|vote|committee|hearing)\b/', $question)) {
            $intent['data_types'][] = 'bill_action';
        }
        
        // Determine temporal scope
        if (preg_match('/\b(recent|recently|latest|new|current|this year|2024|2025)\b/', $question)) {
            $intent['temporal_scope'] = 'recent';
        } elseif (preg_match('/\b(historical|past|previous|last year|2023|2022)\b/', $question)) {
            $intent['temporal_scope'] = 'historical';
        }
        
        // Determine analysis type
        if (preg_match('/\b(how many|count|number|statistics|stats)\b/', $question)) {
            $intent['analysis_type'] = 'quantitative';
        } elseif (preg_match('/\b(compare|versus|vs|difference|similar)\b/', $question)) {
            $intent['analysis_type'] = 'comparative';
        } elseif (preg_match('/\b(trend|pattern|change|over time)\b/', $question)) {
            $intent['analysis_type'] = 'temporal';
        }
        
        // Determine specificity
        if (preg_match('/\b(HR \d+|S \d+|specific|particular)\b/', $question)) {
            $intent['specificity'] = 'specific';
        } elseif (preg_match('/\b(all|every|total|overall)\b/', $question)) {
            $intent['specificity'] = 'comprehensive';
        }
        
        return $intent;
    }

    /**
     * Perform multi-modal retrieval based on intent
     */
    private function performMultiModalRetrieval(string $question, array $intent): array
    {
        $results = [
            'bills' => [],
            'members' => [],
            'actions' => [],
            'sources' => [],
            'stats' => []
        ];
        
        // Semantic search for primary content
        if (in_array('bill', $intent['data_types']) || $intent['primary_focus'] === 'bills') {
            $billResults = $this->semanticSearch->searchBills($question, [
                'limit' => 10,
                'threshold' => 0.7
            ]);
            
            if ($billResults['success']) {
                $results['bills'] = $billResults['bills'];
                $results['sources'][] = "Semantic search: {$billResults['total_found']} bills analyzed";
            }
        }
        
        if (in_array('member', $intent['data_types']) || $intent['primary_focus'] === 'members') {
            $memberResults = $this->semanticSearch->searchMembers($question, [
                'limit' => 8,
                'threshold' => 0.7
            ]);
            
            if ($memberResults['success']) {
                $results['members'] = $memberResults['members'];
                $results['sources'][] = "Member analysis: {$memberResults['total_found']} members reviewed";
            }
        }
        
        // Add keyword-based fallback for better coverage
        $keywordResults = $this->performKeywordSearch($question, $intent);
        $results = $this->mergeResults($results, $keywordResults);
        
        // Add statistical context if needed
        if ($intent['analysis_type'] === 'quantitative') {
            $results['stats'] = $this->gatherStatisticalContext($question, $intent);
        }
        
        return $results;
    }

    /**
     * Keyword-based search as fallback
     */
    private function performKeywordSearch(string $question, array $intent): array
    {
        $keywords = $this->extractKeywords($question);
        $results = ['bills' => [], 'members' => [], 'sources' => []];
        
        if (!empty($keywords) && (in_array('bill', $intent['data_types']) || $intent['primary_focus'] === 'bills')) {
            $query = DB::table('bills');
            
            foreach ($keywords as $keyword) {
                $query->where(function($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                      ->orWhere('summary', 'like', "%{$keyword}%");
                });
            }
            
            if ($intent['temporal_scope'] === 'recent') {
                $query->where('introduced_date', '>=', now()->subYear());
            }
            
            $bills = $query->limit(5)->get();
            
            foreach ($bills as $bill) {
                $results['bills'][] = [
                    'model' => $bill,
                    'similarity' => 0.8, // Estimated for keyword match
                    'match_type' => 'keyword'
                ];
            }
            
            if (count($bills) > 0) {
                $results['sources'][] = "Keyword search: " . count($bills) . " bills found";
            }
        }
        
        return $results;
    }

    /**
     * Extract keywords from question
     */
    private function extractKeywords(string $question): array
    {
        // Remove common stop words and extract meaningful terms
        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'what', 'how', 'when', 'where', 'why', 'who', 'which', 'that', 'this', 'these', 'those', 'are', 'is', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can'];
        
        $words = preg_split('/\s+/', strtolower($question));
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });
        
        return array_values($keywords);
    }

    /**
     * Merge results from different retrieval methods
     */
    private function mergeResults(array $primary, array $secondary): array
    {
        $merged = $primary;
        
        // Merge bills, avoiding duplicates
        $existingBillIds = array_column($primary['bills'], 'id');
        foreach ($secondary['bills'] as $bill) {
            if (!in_array($bill['model']->id ?? null, $existingBillIds)) {
                $merged['bills'][] = $bill;
            }
        }
        
        // Merge sources
        $merged['sources'] = array_merge($primary['sources'], $secondary['sources']);
        
        return $merged;
    }

    /**
     * Gather statistical context for quantitative questions
     */
    private function gatherStatisticalContext(string $question, array $intent): array
    {
        $stats = [];
        
        if ($intent['primary_focus'] === 'bills') {
            $stats['total_bills'] = DB::table('bills')->count();
            $stats['recent_bills'] = DB::table('bills')
                ->where('introduced_date', '>=', now()->subYear())
                ->count();
        }
        
        if ($intent['primary_focus'] === 'members') {
            $stats['total_members'] = DB::table('members')->count();
            $stats['party_breakdown'] = DB::table('members')
                ->select('party_abbreviation', DB::raw('count(*) as count'))
                ->groupBy('party_abbreviation')
                ->get()
                ->toArray();
        }
        
        return $stats;
    }

    /**
     * Synthesize context from retrieval results
     */
    private function synthesizeContext(array $results, array $intent): array
    {
        $context = [
            'relevant_bills' => [],
            'relevant_members' => [],
            'statistics' => $results['stats'] ?? [],
            'data_sources' => $results['sources'],
            'confidence_indicators' => []
        ];
        
        // Process bills with enhanced metadata
        foreach ($results['bills'] as $result) {
            $bill = $result['model'];
            $context['relevant_bills'][] = [
                'id' => $bill->id,
                'title' => $bill->title,
                'number' => $bill->type . ' ' . $bill->number,
                'introduced_date' => $bill->introduced_date,
                'summary' => $bill->summary ?? 'No summary available',
                'similarity_score' => $result['similarity'] ?? 0,
                'match_type' => $result['match_type'] ?? 'semantic'
            ];
        }
        
        // Process members
        foreach ($results['members'] as $result) {
            $member = $result['model'];
            $context['relevant_members'][] = [
                'name' => $member->display_name ?? ($member->first_name . ' ' . $member->last_name),
                'party' => $member->party_abbreviation,
                'state' => $member->state,
                'chamber' => $member->chamber,
                'similarity_score' => $result['similarity'] ?? 0
            ];
        }
        
        return $context;
    }

    /**
     * Generate enhanced response with better prompting
     */
    private function generateEnhancedResponse(string $question, array $context, array $intent): array
    {
        $prompt = $this->buildEnhancedPrompt($question, $context, $intent);
        
        $response = $this->anthropicService->generateResponse($prompt);
        
        return [
            'content' => $response,
            'html' => $this->anthropicService->convertMarkdownToHtml($response)
        ];
    }

    /**
     * Build enhanced prompt with structured context
     */
    private function buildEnhancedPrompt(string $question, array $context, array $intent): string
    {
        $prompt = "You are a congressional data analyst with access to comprehensive legislative information. ";
        $prompt .= "Provide accurate, specific answers based on the provided data.\n\n";
        
        $prompt .= "QUESTION: {$question}\n\n";
        
        $prompt .= "ANALYSIS CONTEXT:\n";
        $prompt .= "- Question Intent: " . json_encode($intent) . "\n";
        $prompt .= "- Data Sources: " . implode(', ', $context['data_sources']) . "\n\n";
        
        if (!empty($context['relevant_bills'])) {
            $prompt .= "RELEVANT BILLS:\n";
            foreach ($context['relevant_bills'] as $i => $bill) {
                $prompt .= ($i + 1) . ". {$bill['number']}: {$bill['title']}\n";
                $prompt .= "   Introduced: {$bill['introduced_date']}\n";
                if (!empty($bill['summary'])) {
                    $prompt .= "   Summary: " . substr($bill['summary'], 0, 200) . "...\n";
                }
                $prompt .= "\n";
            }
        }
        
        if (!empty($context['relevant_members'])) {
            $prompt .= "RELEVANT MEMBERS:\n";
            foreach ($context['relevant_members'] as $i => $member) {
                $prompt .= ($i + 1) . ". {$member['name']} ({$member['party']}-{$member['state']}) - {$member['chamber']}\n";
            }
            $prompt .= "\n";
        }
        
        if (!empty($context['statistics'])) {
            $prompt .= "STATISTICAL CONTEXT:\n";
            foreach ($context['statistics'] as $key => $value) {
                $prompt .= "- {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "INSTRUCTIONS:\n";
        $prompt .= "1. Answer the question directly using the provided data\n";
        $prompt .= "2. Reference specific bills by their numbers (e.g., HR 1234, S 567)\n";
        $prompt .= "3. Include relevant dates, sponsors, and key details\n";
        $prompt .= "4. If data is limited, acknowledge this clearly\n";
        $prompt .= "5. Provide actionable insights when possible\n";
        $prompt .= "6. Format your response clearly with proper structure\n\n";
        
        return $prompt;
    }

    /**
     * Calculate confidence score based on retrieval quality
     */
    private function calculateConfidenceScore(array $results): float
    {
        $totalItems = count($results['bills']) + count($results['members']);
        
        if ($totalItems === 0) {
            return 0.0;
        }
        
        $avgSimilarity = 0;
        $itemCount = 0;
        
        foreach ($results['bills'] as $result) {
            $avgSimilarity += $result['similarity'] ?? 0.5;
            $itemCount++;
        }
        
        foreach ($results['members'] as $result) {
            $avgSimilarity += $result['similarity'] ?? 0.5;
            $itemCount++;
        }
        
        return $itemCount > 0 ? round($avgSimilarity / $itemCount, 3) : 0.0;
    }
}