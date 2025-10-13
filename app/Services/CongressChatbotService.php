<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\Member;
use App\Services\AI\IntelligentQueryService;
use App\Services\SemanticSearchService;
use App\Services\ClaudeSemanticService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class CongressChatbotService
{
    private AnthropicService $anthropicService;
    private DatabaseQueryService $databaseQueryService;
    private IntelligentQueryService $intelligentQueryService;
    private SemanticSearchService $semanticSearchService;
    private ClaudeSemanticService $claudeSemanticService;
    
    public function __construct(
        AnthropicService $anthropicService, 
        DatabaseQueryService $databaseQueryService,
        IntelligentQueryService $intelligentQueryService,
        SemanticSearchService $semanticSearchService,
        ClaudeSemanticService $claudeSemanticService
    ) {
        $this->anthropicService = $anthropicService;
        $this->databaseQueryService = $databaseQueryService;
        $this->intelligentQueryService = $intelligentQueryService;
        $this->semanticSearchService = $semanticSearchService;
        $this->claudeSemanticService = $claudeSemanticService;
    }

    /**
     * Process a user question and generate an AI response with enhanced intelligence
     */
    public function askQuestion(string $question, array $context = []): array
    {
        try {
            // Try Claude semantic search first (preferred)
            if ($this->claudeSemanticService) {
                $claudeResult = $this->askQuestionWithClaudeSemantics($question, $context);
                
                if ($claudeResult['success'] && $claudeResult['method'] === 'claude_semantic') {
                    return $claudeResult;
                }
            }
            
            // Fallback to OpenAI embeddings if available
            if ($this->semanticSearchService) {
                $semanticResult = $this->askQuestionWithSemanticSearch($question, $context);
                
                if ($semanticResult['success'] && $semanticResult['method'] === 'semantic_enhanced') {
                    return $semanticResult;
                }
            }
            
            // Use enhanced intelligent query service with conversation context as fallback
            // Use fast mode for web requests to avoid timeouts
            $fastMode = app()->runningInConsole() ? false : true;
            $queryResult = $this->intelligentQueryService->generateContextualQuery($question, $context, $fastMode);
            
            if ($queryResult['success']) {
                // Execute queries with performance monitoring
                $executionResults = $this->intelligentQueryService->executeQueriesWithMetrics($queryResult['queries']);
                
                // Generate advanced insights with statistical analysis
                $insightsResult = $this->intelligentQueryService->generateAdvancedInsights($question, $executionResults);
                
                return [
                    'success' => true,
                    'response' => $insightsResult['insights'],
                    'response_html' => $insightsResult['insights_html'] ?? $this->anthropicService->convertMarkdownToHtml($insightsResult['insights']),
                    'data_sources' => $this->generateEnhancedDataSources($queryResult['queries']),
                    'queries' => $queryResult['queries'],
                    'query_results' => $executionResults,
                    'analysis_metadata' => $insightsResult['analysis_metadata'] ?? [],
                    'analysis_approach' => $queryResult['analysis_approach'] ?? '',
                    'context_considerations' => $queryResult['context_considerations'] ?? ''
                ];
            } else {
                // Fallback to original database query service
                Log::warning('Enhanced query service failed, falling back to original', [
                    'question' => $question,
                    'error' => $queryResult['error'] ?? 'Unknown error'
                ]);
                
                $result = $this->databaseQueryService->queryDatabase($question);
                
                if ($result['success']) {
                    return [
                        'success' => true,
                        'response' => $result['analysis'],
                        'response_html' => $result['analysis_html'] ?? $this->anthropicService->convertMarkdownToHtml($result['analysis']),
                        'data_sources' => $result['data_sources'],
                        'queries' => $result['queries'],
                        'query_results' => $result['results']
                    ];
                } else {
                    // Final fallback to old method
                    Log::warning('All enhanced methods failed, using fallback', [
                        'question' => $question
                    ]);
                    return $this->askQuestionFallback($question, $context);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Congress chatbot error', [
                'question' => $question,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to process question: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Enhanced question processing with Claude semantic analysis
     */
    private function askQuestionWithClaudeSemantics(string $question, array $context = []): array
    {
        try {
            // Use Claude's hybrid search for comprehensive analysis
            $claudeResults = $this->claudeSemanticService->hybridSearch($question, [
                'limit' => 15,
                'threshold' => 0.3,
                'entity_types' => ['bill', 'member', 'bill_action']
            ]);
            
            if ($claudeResults['success'] && !empty($claudeResults['results'])) {
                // Build context from Claude semantic results
                $claudeContext = $this->buildClaudeSemanticContext($claudeResults);
                
                // Combine with database query for statistical backing
                $databaseResult = $this->databaseQueryService->queryDatabase($question);
                
                // Build enhanced prompt with Claude analysis
                $prompt = $this->buildClaudeEnhancedPrompt($question, $claudeContext, $databaseResult, $context);
                
                $response = $this->anthropicService->generateChatResponse($prompt);
                
                if ($response['success']) {
                    $processedResponse = $this->processResponseWithBillLinks($response['response']);
                    
                    return [
                        'success' => true,
                        'response' => $processedResponse,
                        'response_html' => $this->convertToHtml($processedResponse),
                        'method' => 'claude_semantic',
                        'semantic_results_count' => count($claudeResults['results']),
                        'query_analysis' => $claudeResults['query_analysis'] ?? null,
                        'database_queries' => $databaseResult['queries'] ?? [],
                        'data_sources' => array_merge(
                            ['Claude semantic analysis of congressional content'],
                            $databaseResult['data_sources'] ?? []
                        )
                    ];
                }
            }
            
            // Fallback to next method
            return ['success' => false, 'method' => 'claude_semantic_failed'];
            
        } catch (\Exception $e) {
            Log::error('Claude semantic search chatbot error', [
                'question' => $question,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'method' => 'claude_semantic_failed'];
        }
    }

    /**
     * Enhanced question processing with semantic search
     */
    private function askQuestionWithSemanticSearch(string $question, array $context = []): array
    {
        try {
            // First try semantic search to find relevant content
            $semanticResults = $this->semanticSearchService->search($question, [
                'limit' => 15,
                'threshold' => 0.6,
                'entity_types' => ['bill', 'member', 'bill_action']
            ]);
            
            if ($semanticResults['success'] && !empty($semanticResults['results'])) {
                // Build context from semantic search results
                $semanticContext = $this->buildSemanticContext($semanticResults['results']);
                
                // Combine with database query for comprehensive answer
                $databaseResult = $this->databaseQueryService->queryDatabase($question);
                
                // Build enhanced prompt with both semantic and database context
                $prompt = $this->buildEnhancedPrompt($question, $semanticContext, $databaseResult, $context);
                
                $response = $this->anthropicService->generateChatResponse($prompt);
                
                if ($response['success']) {
                    $processedResponse = $this->processResponseWithBillLinks($response['response']);
                    
                    return [
                        'success' => true,
                        'response' => $processedResponse,
                        'response_html' => $this->convertToHtml($processedResponse),
                        'method' => 'semantic_enhanced',
                        'semantic_results_count' => count($semanticResults['results']),
                        'database_queries' => $databaseResult['queries'] ?? [],
                        'data_sources' => array_merge(
                            ['Semantic search across all congressional content'],
                            $databaseResult['data_sources'] ?? []
                        )
                    ];
                }
            }
            
            // Fallback to database-only approach
            return $this->askQuestionFallback($question, $context);
            
        } catch (\Exception $e) {
            Log::error('Semantic search chatbot error', [
                'question' => $question,
                'error' => $e->getMessage()
            ]);
            
            return $this->askQuestionFallback($question, $context);
        }
    }

    /**
     * Fallback method using the old approach
     */
    private function askQuestionFallback(string $question, array $context = []): array
    {
        try {
            // Analyze the question to determine what data to fetch
            $dataContext = $this->gatherRelevantData($question);
            
            // Build the prompt with data context
            $prompt = $this->buildChatbotPrompt($question, $dataContext, $context);
            
            // Get AI response
            $response = $this->anthropicService->generateChatResponse($prompt);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'response' => $response['response'],
                    'response_html' => $response['response_html'] ?? $this->anthropicService->convertMarkdownToHtml($response['response']),
                    'data_sources' => $dataContext['sources'],
                    'statistics' => $dataContext['statistics']
                ];
            }
            
            return $response;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to process question: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Gather relevant data based on the user's question
     */
    private function gatherRelevantData(string $question): array
    {
        $question = strtolower($question);
        $dataContext = [
            'bills' => [],
            'members' => [],
            'statistics' => [],
            'sources' => []
        ];

        // Detect question type and gather relevant data
        // Use exclusive detection - only one primary type per question
        if ($this->isAboutState($question)) {
            $dataContext = array_merge($dataContext, $this->getStateData($question));
        } elseif ($this->isAboutSpecificBill($question)) {
            $dataContext = array_merge($dataContext, $this->getBillData($question));
        } elseif ($this->isAboutMember($question)) {
            $dataContext = array_merge($dataContext, $this->getMemberData($question));
        } elseif ($this->isAboutParty($question)) {
            $dataContext = array_merge($dataContext, $this->getPartyData($question));
        } elseif ($this->isAboutTrends($question)) {
            $dataContext = array_merge($dataContext, $this->getTrendData($question));
        } elseif ($this->isAboutStatistics($question)) {
            $dataContext = array_merge($dataContext, $this->getGeneralStatistics());
        } elseif ($this->isAboutTopic($question)) {
            // Check for topic-based searches (China, healthcare, etc.)
            $dataContext = array_merge($dataContext, $this->getTopicData($question));
        } else {
            // Default to general statistics if no specific type detected
            $dataContext = array_merge($dataContext, $this->getGeneralStatistics());
        }

        return $dataContext;
    }

    /**
     * Check if question is about a specific bill
     */
    private function isAboutSpecificBill(string $question): bool
    {
        return preg_match('/\b(hr|h\.r\.|s\.|senate|house)\s*\d+/i', $question) ||
               preg_match('/\bbill\s+(hr|h\.r\.|s\.)\s*\d+/i', $question) ||
               str_contains($question, 'specific bill') ||
               str_contains($question, 'this bill');
    }

    /**
     * Check if question is about members
     */
    private function isAboutMember(string $question): bool
    {
        return str_contains($question, 'senator') ||
               str_contains($question, 'representative') ||
               str_contains($question, 'congressman') ||
               str_contains($question, 'congresswoman') ||
               str_contains($question, 'member') ||
               preg_match('/\b[A-Z][a-z]+\s+[A-Z][a-z]+\b/', $question); // Name pattern
    }

    /**
     * Check if question is about party analysis
     */
    private function isAboutParty(string $question): bool
    {
        return str_contains($question, 'democrat') ||
               str_contains($question, 'republican') ||
               str_contains($question, 'party') ||
               str_contains($question, 'partisan') ||
               str_contains($question, 'bipartisan');
    }

    /**
     * Check if question is about state representation
     */
    private function isAboutState(string $question): bool
    {
        $states = [
            'alabama', 'alaska', 'arizona', 'arkansas', 'california', 'colorado', 'connecticut', 
            'delaware', 'florida', 'georgia', 'hawaii', 'idaho', 'illinois', 'indiana', 'iowa', 
            'kansas', 'kentucky', 'louisiana', 'maine', 'maryland', 'massachusetts', 'michigan', 
            'minnesota', 'mississippi', 'missouri', 'montana', 'nebraska', 'nevada', 'new hampshire', 
            'new jersey', 'new mexico', 'new york', 'north carolina', 'north dakota', 'ohio', 
            'oklahoma', 'oregon', 'pennsylvania', 'rhode island', 'south carolina', 'south dakota', 
            'tennessee', 'texas', 'utah', 'vermont', 'virginia', 'washington', 'west virginia', 
            'wisconsin', 'wyoming'
        ];
        
        foreach ($states as $state) {
            if (str_contains($question, $state)) return true;
        }
        
        return (str_contains($question, 'state') && (str_contains($question, 'represent') || str_contains($question, 'from'))) ||
               str_contains($question, 'which states') ||
               str_contains($question, 'states have') ||
               (str_contains($question, 'republican') && str_contains($question, 'representatives')) ||
               (str_contains($question, 'democratic') && str_contains($question, 'representatives')) ||
               (str_contains($question, 'democrat') && str_contains($question, 'representatives'));
    }

    /**
     * Check if question is about trends
     */
    private function isAboutTrends(string $question): bool
    {
        // Don't catch state-related questions that happen to contain "most"
        if ($this->isAboutState($question)) {
            return false;
        }
        
        return str_contains($question, 'trend') ||
               str_contains($question, 'recent') ||
               str_contains($question, 'lately') ||
               str_contains($question, 'this year') ||
               str_contains($question, 'popular') ||
               (str_contains($question, 'most') && !str_contains($question, 'states'));
    }

    /**
     * Check if question is about general statistics
     */
    private function isAboutStatistics(string $question): bool
    {
        return str_contains($question, 'how many') ||
               str_contains($question, 'statistics') ||
               str_contains($question, 'numbers') ||
               str_contains($question, 'count') ||
               str_contains($question, 'total');
    }

    /**
     * Check if question is about a specific topic/subject
     */
    private function isAboutTopic(string $question): bool
    {
        $topicKeywords = [
            'china', 'chinese', 'taiwan', 'beijing',
            'healthcare', 'health care', 'medical', 'medicare', 'medicaid',
            'climate', 'environment', 'energy', 'renewable',
            'immigration', 'border', 'visa', 'refugee',
            'education', 'school', 'student', 'university',
            'tax', 'taxes', 'taxation', 'revenue',
            'defense', 'military', 'army', 'navy', 'air force',
            'infrastructure', 'roads', 'bridges', 'transportation',
            'technology', 'tech', 'artificial intelligence', 'ai', 'cyber',
            'trade', 'tariff', 'export', 'import',
            'agriculture', 'farming', 'food', 'rural',
            'trump', 'biden', 'president', 'presidential', 'executive'
        ];
        
        foreach ($topicKeywords as $keyword) {
            if (str_contains($question, $keyword)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if question contains subjective or biased language
     */
    private function containsSubjectiveLanguage(string $question): bool
    {
        $subjectiveTerms = [
            'make', 'look good', 'look bad', 'help', 'hurt', 'benefit', 'harm',
            'favor', 'oppose', 'support', 'attack', 'defend', 'promote', 'undermine'
        ];
        
        foreach ($subjectiveTerms as $term) {
            if (str_contains($question, $term)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get bill-specific data
     */
    private function getBillData(string $question): array
    {
        // Extract bill number if mentioned
        preg_match('/\b(hr|h\.r\.|s\.)\s*(\d+)/i', $question, $matches);
        
        $data = ['bills' => [], 'sources' => []];
        
        if (!empty($matches)) {
            $type = strtolower($matches[1]);
            $number = $matches[2];
            
            $bill = Bill::where('number', $number)
                       ->where('type', $type === 's.' ? 'S' : 'HR')
                       ->first();
                       
            if ($bill) {
                $data['bills'][] = $bill->toArray();
                $data['sources'][] = "Bill {$bill->type} {$bill->number}: {$bill->title}";
            }
        } else {
            // Get recent bills if no specific bill mentioned
            $recentBills = Bill::orderBy('introduced_date', 'desc')
                              ->limit(5)
                              ->get()
                              ->toArray();
            $data['bills'] = $recentBills;
            $data['sources'][] = "Recent 5 bills from database";
        }
        
        return $data;
    }

    /**
     * Get member-specific data
     */
    private function getMemberData(string $question): array
    {
        $data = ['members' => [], 'sources' => []];
        
        // Try to extract member name
        preg_match('/\b([A-Z][a-z]+)\s+([A-Z][a-z]+)\b/', $question, $matches);
        
        if (!empty($matches)) {
            $firstName = $matches[1];
            $lastName = $matches[2];
            
            $member = Member::where('first_name', 'like', "%{$firstName}%")
                           ->where('last_name', 'like', "%{$lastName}%")
                           ->first();
                           
            if ($member) {
                $memberArray = $member->toArray();
                $memberArray['display_name'] = $member->display_name;
                $memberArray['chamber_display'] = $member->chamber_display;
                $data['members'][] = $memberArray;
                $data['sources'][] = "Member profile: {$member->display_name}";
            }
        } else {
            // Get sample of current members
            $members = Member::current()->limit(10)->get();
            $membersArray = [];
            foreach ($members as $member) {
                $memberArray = $member->toArray();
                $memberArray['display_name'] = $member->display_name;
                $memberArray['chamber_display'] = $member->chamber_display;
                $membersArray[] = $memberArray;
            }
            $data['members'] = $membersArray;
            $data['sources'][] = "Sample of current members";
        }
        
        return $data;
    }

    /**
     * Get party-related data
     */
    private function getPartyData(string $question): array
    {
        $stats = [
            'party_breakdown' => Member::current()
                ->select('party_abbreviation', 'party_name', DB::raw('count(*) as count'))
                ->groupBy('party_abbreviation', 'party_name')
                ->get()
                ->toArray(),
            'chamber_party_breakdown' => Member::current()
                ->select('chamber', 'party_abbreviation', DB::raw('count(*) as count'))
                ->groupBy('chamber', 'party_abbreviation')
                ->get()
                ->toArray()
        ];
        
        return [
            'statistics' => $stats,
            'sources' => ['Current member party breakdown by chamber']
        ];
    }

    /**
     * Get state representation data
     */
    private function getStateData(string $question): array
    {
        $data = ['members' => [], 'statistics' => [], 'sources' => []];
        
        // Get comprehensive state-party breakdown
        $statePartyBreakdown = Member::current()
            ->select('state', 'party_abbreviation', DB::raw('count(*) as count'))
            ->groupBy('state', 'party_abbreviation')
            ->orderBy('state')
            ->get()
            ->groupBy('state')
            ->toArray();
            
        // Get Republican representatives by state (sorted by count)
        $republicansByState = Member::current()
            ->where('party_abbreviation', 'R')
            ->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
            
        // Get Democratic representatives by state (sorted by count)
        $democratsByState = Member::current()
            ->where('party_abbreviation', 'D')
            ->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
            
        // Get total representation by state
        $totalByState = Member::current()
            ->select('state', DB::raw('count(*) as count'))
            ->groupBy('state')
            ->orderBy('count', 'desc')
            ->get()
            ->toArray();
            
        // Get chamber breakdown by state
        $chamberByState = Member::current()
            ->select('state', 'chamber', DB::raw('count(*) as count'))
            ->groupBy('state', 'chamber')
            ->orderBy('state')
            ->get()
            ->groupBy('state')
            ->toArray();
            
        // If question is specifically about Republicans, get sample Republican members
        if (str_contains($question, 'republican')) {
            $republicanMembers = Member::current()
                ->where('party_abbreviation', 'R')
                ->orderBy('state')
                ->limit(20)
                ->get();
                
            $membersArray = [];
            foreach ($republicanMembers as $member) {
                $memberArray = $member->toArray();
                $memberArray['display_name'] = $member->display_name;
                $memberArray['chamber_display'] = $member->chamber_display;
                $membersArray[] = $memberArray;
            }
            $data['members'] = $membersArray;
            $data['sources'][] = "Sample of Republican members by state";
        }
        
        // If question is specifically about Democrats, get sample Democratic members
        if (str_contains($question, 'democrat')) {
            $democraticMembers = Member::current()
                ->where('party_abbreviation', 'D')
                ->orderBy('state')
                ->limit(20)
                ->get();
                
            $membersArray = [];
            foreach ($democraticMembers as $member) {
                $memberArray = $member->toArray();
                $memberArray['display_name'] = $member->display_name;
                $memberArray['chamber_display'] = $member->chamber_display;
                $membersArray[] = $memberArray;
            }
            $data['members'] = $membersArray;
            $data['sources'][] = "Sample of Democratic members by state";
        }
        
        $data['statistics'] = [
            'republicans_by_state' => $republicansByState,
            'democrats_by_state' => $democratsByState,
            'total_by_state' => $totalByState,
            'state_party_breakdown' => $statePartyBreakdown,
            'chamber_by_state' => $chamberByState
        ];
        
        $data['sources'][] = 'Complete state representation analysis';
        
        return $data;
    }

    /**
     * Get trend data
     */
    private function getTrendData(string $question): array
    {
        // Use SQLite-compatible date formatting
        $dateFormat = config('database.default') === 'mysql' 
            ? 'DATE_FORMAT(introduced_date, "%Y-%m")' 
            : 'strftime("%Y-%m", introduced_date)';
            
        $stats = [
            'recent_bills_by_month' => Bill::select(
                DB::raw($dateFormat . ' as month'),
                DB::raw('count(*) as count')
            )
            ->where('introduced_date', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->get()
            ->toArray(),
            
            'popular_policy_areas' => Bill::select('policy_area', DB::raw('count(*) as count'))
                ->whereNotNull('policy_area')
                ->where('introduced_date', '>=', now()->subMonths(6))
                ->groupBy('policy_area')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray(),
                
            'most_active_sponsors' => Member::select('members.full_name', 'members.party_abbreviation', 'members.state', DB::raw('count(*) as bills_sponsored'))
                ->join('bill_sponsors', 'members.bioguide_id', '=', 'bill_sponsors.bioguide_id')
                ->join('bills', 'bill_sponsors.bill_id', '=', 'bills.id')
                ->where('bills.introduced_date', '>=', now()->subMonths(6))
                ->groupBy('members.bioguide_id', 'members.full_name', 'members.party_abbreviation', 'members.state')
                ->orderBy('bills_sponsored', 'desc')
                ->limit(10)
                ->get()
                ->toArray()
        ];
        
        return [
            'statistics' => $stats,
            'sources' => ['Recent legislative trends and activity patterns']
        ];
    }

    /**
     * Get general statistics
     */
    private function getGeneralStatistics(): array
    {
        $stats = [
            'total_bills' => Bill::count(),
            'bills_this_congress' => Bill::where('congress', 118)->count(),
            'total_members' => Member::count(),
            'current_members' => Member::current()->count(),
            'bills_by_chamber' => Bill::select('origin_chamber', DB::raw('count(*) as count'))
                ->groupBy('origin_chamber')
                ->get()
                ->toArray(),
            'bills_by_type' => Bill::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->get()
                ->toArray()
        ];
        
        return [
            'statistics' => $stats,
            'sources' => ['General congressional statistics']
        ];
    }

    /**
     * Get topic-specific data by searching bill content
     */
    private function getTopicData(string $question): array
    {
        $data = ['bills' => [], 'sources' => []];
        
        // Extract topic keywords from the question
        $keywords = $this->extractTopicKeywords($question);
        
        if (empty($keywords)) {
            return $data;
        }
        
        // Search bills by title, policy area, and bill text
        $query = Bill::query();
        
        foreach ($keywords as $keyword) {
            $query->orWhere('title', 'like', "%{$keyword}%")
                  ->orWhere('short_title', 'like', "%{$keyword}%")
                  ->orWhere('policy_area', 'like', "%{$keyword}%")
                  ->orWhere('bill_text', 'like', "%{$keyword}%")
                  ->orWhere('ai_summary', 'like', "%{$keyword}%");
        }
        
        $bills = $query->orderBy('introduced_date', 'desc')
                      ->limit(10)
                      ->get();
        
        if ($bills->count() > 0) {
            $data['bills'] = $bills->toArray();
            $data['sources'][] = "Bills related to: " . implode(', ', $keywords) . " ({$bills->count()} found)";
            
            // Add some statistics about the topic
            $data['statistics'] = [
                'total_found' => $bills->count(),
                'by_type' => $bills->groupBy('type')->map->count()->toArray(),
                'by_chamber' => $bills->groupBy('origin_chamber')->map->count()->toArray(),
                'recent_count' => $bills->where('introduced_date', '>=', now()->subMonths(6))->count(),
                'policy_areas' => $bills->whereNotNull('policy_area')->groupBy('policy_area')->map->count()->toArray(),
                'search_keywords' => $keywords
            ];
            
            // Also get general statistics for context if we found bills
            $generalStats = $this->getGeneralStatistics();
            $data['statistics']['context'] = $generalStats['statistics'];
        } else {
            $data['sources'][] = "No bills found for: " . implode(', ', $keywords);
            
            // Still provide general statistics for context
            $generalStats = $this->getGeneralStatistics();
            $data['statistics'] = array_merge($generalStats['statistics'], [
                'search_keywords' => $keywords,
                'total_found' => 0
            ]);
        }
        
        return $data;
    }

    /**
     * Extract topic keywords from question
     */
    private function extractTopicKeywords(string $question): array
    {
        $question = strtolower($question);
        $keywords = [];
        
        // Define topic mappings with related terms
        $topicMappings = [
            'china' => ['china', 'chinese', 'beijing', 'prc', 'people\'s republic'],
            'taiwan' => ['taiwan', 'taiwanese', 'republic of china', 'formosa'],
            'healthcare' => ['healthcare', 'health care', 'medical', 'medicare', 'medicaid', 'hospital', 'doctor', 'patient'],
            'climate' => ['climate', 'environment', 'environmental', 'carbon', 'emissions', 'global warming'],
            'energy' => ['energy', 'renewable', 'solar', 'wind', 'oil', 'gas', 'nuclear', 'coal'],
            'immigration' => ['immigration', 'immigrant', 'border', 'visa', 'refugee', 'asylum', 'deportation'],
            'education' => ['education', 'school', 'student', 'teacher', 'university', 'college', 'learning'],
            'tax' => ['tax', 'taxes', 'taxation', 'revenue', 'irs', 'deduction', 'credit'],
            'defense' => ['defense', 'military', 'army', 'navy', 'air force', 'marines', 'pentagon', 'security'],
            'infrastructure' => ['infrastructure', 'roads', 'bridges', 'transportation', 'highway', 'transit'],
            'technology' => ['technology', 'tech', 'artificial intelligence', 'ai', 'cyber', 'internet', 'digital'],
            'trade' => ['trade', 'tariff', 'export', 'import', 'commerce', 'wto', 'nafta'],
            'agriculture' => ['agriculture', 'farming', 'food', 'rural', 'crop', 'livestock', 'farm'],
            'trump' => ['trump', 'donald trump', 'former president trump'],
            'biden' => ['biden', 'joe biden', 'president biden'],
            'presidential' => ['president', 'presidential', 'executive', 'white house', 'administration']
        ];
        
        // Find matching topics
        foreach ($topicMappings as $topic => $terms) {
            foreach ($terms as $term) {
                if (str_contains($question, $term)) {
                    $keywords[] = $topic;
                    // Also add the specific term found
                    if ($term !== $topic) {
                        $keywords[] = $term;
                    }
                    break; // Found this topic, move to next
                }
            }
        }
        
        return array_unique($keywords);
    }

    /**
     * Generate enhanced data sources with metadata
     */
    private function generateEnhancedDataSources(array $queries): array
    {
        $sources = [];
        foreach ($queries as $query) {
            $source = $query['description'];
            
            // Add query type and complexity info
            if (isset($query['query_type'])) {
                $source .= " ({$query['query_type']} analysis)";
            }
            
            if (isset($query['complexity']) && $query['complexity'] === 'complex') {
                $source .= " - Advanced statistical analysis";
            }
            
            $sources[] = $source;
        }
        return $sources;
    }

    /**
     * Build the chatbot prompt with data context
     */
    private function buildChatbotPrompt(string $question, array $dataContext, array $conversationContext = []): string
    {
        $prompt = "You are a knowledgeable congressional data analyst AI assistant. You have access to comprehensive data about U.S. Congress bills and members. Answer the user's question using the provided data context, and provide insights, analysis, and specific examples from the data.

USER QUESTION: {$question}

DATA CONTEXT:
";

        // Add bills data if available
        if (!empty($dataContext['bills'])) {
            $prompt .= "\nRELEVANT BILLS:\n";
            foreach (array_slice($dataContext['bills'], 0, 5) as $bill) {
                $prompt .= "- {$bill['type']} {$bill['number']}: {$bill['title']}\n";
                $prompt .= "  Introduced: {$bill['introduced_date']}, Policy Area: {$bill['policy_area']}\n";
                if (!empty($bill['ai_summary'])) {
                    $prompt .= "  Summary: " . substr($bill['ai_summary'], 0, 200) . "...\n";
                }
                $prompt .= "\n";
            }
        }

        // Add members data if available
        if (!empty($dataContext['members'])) {
            $prompt .= "\nRELEVANT MEMBERS:\n";
            foreach (array_slice($dataContext['members'], 0, 10) as $member) {
                $prompt .= "- {$member['display_name']} ({$member['party_abbreviation']}-{$member['state']})\n";
                $prompt .= "  Chamber: {$member['chamber_display']}, Current: " . ($member['current_member'] ? 'Yes' : 'No') . "\n";
                $prompt .= "  Sponsored: {$member['sponsored_legislation_count']}, Cosponsored: {$member['cosponsored_legislation_count']}\n\n";
            }
        }

        // Add statistics if available
        if (!empty($dataContext['statistics'])) {
            $prompt .= "\nRELEVANT STATISTICS:\n";
            foreach ($dataContext['statistics'] as $key => $stats) {
                $prompt .= "\n{$key}:\n";
                if (is_array($stats)) {
                    foreach (array_slice($stats, 0, 10) as $stat) {
                        if (is_array($stat)) {
                            $prompt .= "  " . json_encode($stat) . "\n";
                        } else {
                            $prompt .= "  {$stat}\n";
                        }
                    }
                }
            }
        }

        // Check if question contains subjective language
        $isSubjective = $this->containsSubjectiveLanguage($question);
        
        $prompt .= "\nINSTRUCTIONS:
1. Answer the question directly and comprehensively using the provided data
2. Include specific examples, numbers, and insights from the data
3. If the data shows interesting patterns or trends, highlight them
4. Be conversational but informative
5. If the data is limited for the question, acknowledge this and provide what insights you can
6. Use markdown formatting for better readability
7. Cite specific bills, members, or statistics when relevant
8. FORMATTING: Structure your response with proper formatting:
   - Use paragraph breaks to separate different topics
   - Use bullet points (-) for lists and key points
   - Use **bold** for emphasis on important information
   - Break up long responses into digestible sections
   - When mentioning bills, use format: **[Bill Type] [Number]: [Title]**";

        if ($isSubjective) {
            $prompt .= "
8. IMPORTANT: This question contains subjective language. Focus on objective, factual analysis only:
   - Present factual information about bills and their content
   - Avoid making subjective judgments about political intent or outcomes
   - If asked about political figures, focus on measurable legislative activity
   - Suggest more objective ways to analyze the data
   - Remain politically neutral and fact-based";
        }

        $prompt .= "\n\nPlease provide a helpful, data-driven response to the user's question.";

        return $prompt;
    }

    /**
     * Build context from semantic search results
     */
    private function buildSemanticContext(array $semanticResults): array
    {
        $context = [
            'bills' => [],
            'members' => [],
            'actions' => [],
            'sources' => []
        ];
        
        foreach ($semanticResults as $result) {
            $similarity = round($result['similarity'] * 100, 1);
            
            switch ($result['entity_type']) {
                case 'bill':
                    if (isset($result['model'])) {
                        $bill = $result['model'];
                        $billData = $bill->toArray();
                        $billData['semantic_similarity'] = $similarity;
                        $context['bills'][] = $billData;
                        $context['sources'][] = "Bill {$bill->congress_id} ({$similarity}% semantic match)";
                    }
                    break;
                    
                case 'member':
                    if (isset($result['model'])) {
                        $member = $result['model'];
                        $memberData = $member->toArray();
                        $memberData['semantic_similarity'] = $similarity;
                        $context['members'][] = $memberData;
                        $context['sources'][] = "Member {$member->display_name} ({$similarity}% semantic match)";
                    }
                    break;
                    
                case 'bill_action':
                    if (isset($result['model'])) {
                        $action = $result['model'];
                        $actionData = $action->toArray();
                        $actionData['semantic_similarity'] = $similarity;
                        $context['actions'][] = $actionData;
                        $context['sources'][] = "Legislative action ({$similarity}% semantic match)";
                    }
                    break;
            }
        }
        
        return $context;
    }

    /**
     * Build enhanced prompt combining semantic and database results
     */
    private function buildEnhancedPrompt(string $question, array $semanticContext, array $databaseResult, array $conversationContext = []): string
    {
        $prompt = "You are a knowledgeable congressional data analyst AI assistant with access to comprehensive semantic search and database query capabilities. Answer the user's question using both the semantic search results and database analysis provided.

USER QUESTION: {$question}

SEMANTIC SEARCH RESULTS (Most Relevant Content):
";

        // Add semantic bills
        if (!empty($semanticContext['bills'])) {
            $prompt .= "\nSEMANTICALLY RELEVANT BILLS:\n";
            foreach (array_slice($semanticContext['bills'], 0, 5) as $bill) {
                $similarity = $bill['semantic_similarity'] ?? 0;
                $prompt .= "- {$bill['congress_id']} ({$similarity}% match): {$bill['title']}\n";
                if (!empty($bill['policy_area'])) {
                    $prompt .= "  Policy Area: {$bill['policy_area']}\n";
                }
                if (!empty($bill['ai_summary'])) {
                    $prompt .= "  Summary: " . substr($bill['ai_summary'], 0, 200) . "...\n";
                }
                $prompt .= "\n";
            }
        }

        // Add semantic members
        if (!empty($semanticContext['members'])) {
            $prompt .= "\nSEMANTICALLY RELEVANT MEMBERS:\n";
            foreach (array_slice($semanticContext['members'], 0, 5) as $member) {
                $similarity = $member['semantic_similarity'] ?? 0;
                $prompt .= "- {$member['full_name']} ({$similarity}% match): {$member['party_abbreviation']}-{$member['state']}\n";
                $prompt .= "  Chamber: {$member['chamber']}, Sponsored: {$member['sponsored_legislation_count']} bills\n\n";
            }
        }

        // Add database analysis if available
        if ($databaseResult['success'] ?? false) {
            $prompt .= "\nDATABASE ANALYSIS:\n";
            if (!empty($databaseResult['analysis'])) {
                $prompt .= $databaseResult['analysis'] . "\n\n";
            }
            
            if (!empty($databaseResult['results'])) {
                $prompt .= "Database Query Results:\n";
                foreach ($databaseResult['results'] as $queryName => $result) {
                    if (!isset($result['error']) && $result['count'] > 0) {
                        $prompt .= "- {$result['description']}: {$result['count']} records\n";
                    }
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "
INSTRUCTIONS:
1. Provide a comprehensive answer using both semantic search results and database analysis
2. Prioritize the most semantically relevant content (highest similarity scores)
3. Include specific examples from the bills, members, or actions found
4. When mentioning bills, use the format: **[Bill Type] [Number]: [Title]** (we'll add links later)
5. Be conversational and easy to understand
6. If you find relevant patterns or connections, highlight them
7. Use the database analysis to provide statistical context
8. Acknowledge if the semantic search found particularly relevant content
9. FORMATTING: Use proper paragraph breaks, bullet points, and structure:
   - Separate different topics with blank lines
   - Use bullet points (-) for lists
   - Use **bold** for emphasis on important points
   - Break up long responses into digestible paragraphs

Please provide a helpful, well-formatted response combining both semantic and database insights.";

        return $prompt;
    }

    /**
     * Build context from Claude semantic search results
     */
    private function buildClaudeSemanticContext(array $claudeResults): array
    {
        $context = [
            'bills' => [],
            'members' => [],
            'actions' => [],
            'sources' => []
        ];
        
        if (!isset($claudeResults['results'])) {
            return $context;
        }
        
        foreach ($claudeResults['results'] as $result) {
            $similarity = round($result['similarity'] * 100, 1);
            
            switch ($result['entity_type']) {
                case 'bill':
                    if (isset($result['model'])) {
                        $bill = $result['model'];
                        $billData = $bill->toArray();
                        $billData['semantic_similarity'] = $similarity;
                        $context['bills'][] = $billData;
                        $context['sources'][] = "Bill {$bill->congress_id} ({$similarity}% Claude semantic match)";
                    }
                    break;
                    
                case 'member':
                    if (isset($result['model'])) {
                        $member = $result['model'];
                        $memberData = $member->toArray();
                        $memberData['semantic_similarity'] = $similarity;
                        $context['members'][] = $memberData;
                        $context['sources'][] = "Member {$member->display_name} ({$similarity}% Claude semantic match)";
                    }
                    break;
                    
                case 'bill_action':
                    if (isset($result['model'])) {
                        $action = $result['model'];
                        $actionData = $action->toArray();
                        $actionData['semantic_similarity'] = $similarity;
                        $context['actions'][] = $actionData;
                        $context['sources'][] = "Legislative action ({$similarity}% Claude semantic match)";
                    }
                    break;
            }
        }
        
        return $context;
    }

    /**
     * Build enhanced prompt with Claude semantic analysis
     */
    private function buildClaudeEnhancedPrompt(string $question, array $claudeContext, array $databaseResult, array $conversationContext = []): string
    {
        $prompt = "You are a knowledgeable congressional data analyst AI assistant with access to advanced Claude semantic search and database query capabilities. Answer the user's question using both the Claude semantic analysis and database results provided.

USER QUESTION: {$question}

CLAUDE SEMANTIC ANALYSIS RESULTS (Most Relevant Content):
";

        // Add Claude semantic bills
        if (!empty($claudeContext['bills'])) {
            $prompt .= "\nSEMANTICALLY RELEVANT BILLS (Claude Analysis):\n";
            foreach (array_slice($claudeContext['bills'], 0, 5) as $bill) {
                $similarity = $bill['semantic_similarity'] ?? 0;
                $prompt .= "- {$bill['congress_id']} ({$similarity}% match): {$bill['title']}\n";
                if (!empty($bill['policy_area'])) {
                    $prompt .= "  Policy Area: {$bill['policy_area']}\n";
                }
                if (!empty($bill['ai_summary'])) {
                    $prompt .= "  Summary: " . substr($bill['ai_summary'], 0, 200) . "...\n";
                }
                $prompt .= "\n";
            }
        }

        // Add Claude semantic members
        if (!empty($claudeContext['members'])) {
            $prompt .= "\nSEMANTICALLY RELEVANT MEMBERS (Claude Analysis):\n";
            foreach (array_slice($claudeContext['members'], 0, 5) as $member) {
                $similarity = $member['semantic_similarity'] ?? 0;
                $prompt .= "- {$member['full_name']} ({$similarity}% match): {$member['party_abbreviation']}-{$member['state']}\n";
                $prompt .= "  Chamber: {$member['chamber']}, Sponsored: {$member['sponsored_legislation_count']} bills\n\n";
            }
        }

        // Add database analysis if available
        if ($databaseResult['success'] ?? false) {
            $prompt .= "\nDATABASE ANALYSIS:\n";
            if (!empty($databaseResult['analysis'])) {
                $prompt .= $databaseResult['analysis'] . "\n\n";
            }
            
            if (!empty($databaseResult['results'])) {
                $prompt .= "Database Query Results:\n";
                foreach ($databaseResult['results'] as $queryName => $result) {
                    if (!isset($result['error']) && $result['count'] > 0) {
                        $prompt .= "- {$result['description']}: {$result['count']} records\n";
                    }
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "
INSTRUCTIONS:
1. Provide a comprehensive answer using both Claude semantic analysis and database results
2. Prioritize the most semantically relevant content (highest similarity scores from Claude)
3. Include specific examples from the bills, members, or actions found
4. When mentioning bills, use the format: **[Bill Type] [Number]: [Title]** (we'll add links later)
5. Be conversational and easy to understand
6. If you find relevant patterns or connections, highlight them
7. Use the database analysis to provide statistical context
8. Acknowledge if Claude's semantic search found particularly relevant content
9. FORMATTING: Use proper paragraph breaks, bullet points, and structure:
   - Separate different topics with blank lines
   - Use bullet points (-) for lists
   - Use **bold** for emphasis on important points
   - Break up long responses into digestible paragraphs

Please provide a helpful, well-formatted response combining both Claude semantic insights and database analysis.";

        return $prompt;
    }

    /**
     * Process response to add bill links and formatting
     */
    private function processResponseWithBillLinks(string $response): string
    {
        // This method processes the AI response to add bill links
        // The actual link processing is done in the ChatbotController
        // Here we just clean up the response format
        
        // Clean up any technical references
        $patterns = [
            '/\b(SQL|query|queries|database|table|JOIN|SELECT|WHERE|FROM|GROUP BY|ORDER BY)\b/i' => '',
            '/Generated \d+ optimized queries?/i' => '',
            '/Query Results Summary:.*?🤖/s' => '🤖',
            '/📝 Generated.*?queries?:.*?(?=📊|🤖|$)/s' => '',
            '/Based on the database query results?/i' => 'Based on the congressional data analysis',
            '/querying the database/i' => 'analyzing the data',
            '/database analysis/i' => 'data analysis',
            '/query results/i' => 'analysis results',
        ];
        
        $cleanResponse = $response;
        foreach ($patterns as $pattern => $replacement) {
            $cleanResponse = preg_replace($pattern, $replacement, $cleanResponse);
        }
        
        // Clean up extra whitespace
        $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);
        $cleanResponse = preg_replace('/\s*\n\s*/', "\n", $cleanResponse);
        $cleanResponse = trim($cleanResponse);
        
        return $cleanResponse;
    }

    /**
     * Convert markdown response to HTML with proper formatting
     */
    private function convertToHtml(string $response): string
    {
        // Use the AnthropicService's markdown conversion if available
        if (method_exists($this->anthropicService, 'convertMarkdownToHtml')) {
            return $this->anthropicService->convertMarkdownToHtml($response);
        }
        
        // Enhanced markdown to HTML conversion
        $html = $response;
        
        // Convert headers (with proper spacing)
        $html = preg_replace('/^### (.*$)/m', '<h3 class="text-lg font-semibold mt-4 mb-2">$1</h3>', $html);
        $html = preg_replace('/^## (.*$)/m', '<h2 class="text-xl font-semibold mt-6 mb-3">$1</h2>', $html);
        $html = preg_replace('/^# (.*$)/m', '<h1 class="text-2xl font-bold mt-6 mb-4">$1</h1>', $html);
        
        // Convert numbered lists
        $html = preg_replace('/^\d+\.\s+(.*$)/m', '<li class="mb-1">$1</li>', $html);
        
        // Convert bullet points
        $html = preg_replace('/^[-*]\s+(.*$)/m', '<li class="mb-1">$1</li>', $html);
        
        // Wrap consecutive list items in ul tags
        $html = preg_replace('/(<li class="mb-1">.*?<\/li>)(\s*<li class="mb-1">.*?<\/li>)*/s', '<ul class="list-disc list-inside mb-4 space-y-1">$0</ul>', $html);
        
        // Convert bold text
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-semibold">$1</strong>', $html);
        
        // Convert italic text
        $html = preg_replace('/\*(.*?)\*/', '<em class="italic">$1</em>', $html);
        
        // Convert code blocks
        $html = preg_replace('/```(.*?)```/s', '<pre class="bg-gray-100 p-3 rounded mb-4 overflow-x-auto"><code>$1</code></pre>', $html);
        
        // Convert inline code
        $html = preg_replace('/`([^`]+)`/', '<code class="bg-gray-100 px-1 rounded">$1</code>', $html);
        
        // Split into paragraphs and add proper spacing
        $paragraphs = preg_split('/\n\s*\n/', $html);
        $formattedParagraphs = [];
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Don't wrap headers, lists, or pre-formatted content in p tags
                if (!preg_match('/^<(h[1-6]|ul|ol|li|pre|div)/', $paragraph)) {
                    $paragraph = '<p class="mb-4 leading-relaxed">' . $paragraph . '</p>';
                }
                $formattedParagraphs[] = $paragraph;
            }
        }
        
        $html = implode("\n", $formattedParagraphs);
        
        // Convert remaining line breaks to br tags (for content within paragraphs)
        $html = preg_replace('/(?<!>)\n(?!<)/', '<br>', $html);
        
        return $html;
    }
}