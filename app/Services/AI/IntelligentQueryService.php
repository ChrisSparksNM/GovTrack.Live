<?php

namespace App\Services\AI;

use App\Services\AnthropicService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IntelligentQueryService
{
    private AnthropicService $anthropicService;

    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
    }

    /**
     * Generate intelligent queries with context awareness
     */
    public function generateContextualQuery(string $question, array $conversationHistory = [], bool $fastMode = false): array
    {
        if ($fastMode) {
            return $this->generateFastQuery($question);
        }
        
        $context = $this->buildConversationContext($conversationHistory);
        $schema = $this->getEnhancedSchema();
        
        $prompt = "Generate SQL queries to answer this congressional data question.

QUESTION: {$question}

DATABASE SCHEMA (key tables):
- bills: id, congress, type, title, policy_area, introduced_date, cosponsors_count
- members: bioguide_id, party_abbreviation, state, chamber, current_member
- bill_sponsors: bill_id, bioguide_id, party, state
- bill_cosponsors: bill_id, bioguide_id, party, sponsorship_date

INSTRUCTIONS:
1. Generate 1-2 focused SQL queries
2. Use proper SQLite syntax with strftime() for dates
3. Include GROUP BY and ORDER BY for analysis
4. Use LIMIT for top/most questions

RETURN ONLY THIS JSON FORMAT:
```json
{
  \"queries\": [
    {
      \"name\": \"main_analysis\",
      \"description\": \"Primary analysis query\",
      \"sql\": \"SELECT ... FROM ... WHERE ... GROUP BY ... ORDER BY ... LIMIT 20\",
      \"query_type\": \"statistical\",
      \"complexity\": \"moderate\"
    }
  ]
}
```";

        try {
            $response = $this->anthropicService->generateChatResponse($prompt);
            
            if ($response['success']) {
                return $this->parseEnhancedQueryResponse($response['response']);
            }

            Log::warning('Anthropic API failed for enhanced queries', [
                'error' => $response['error'] ?? 'Unknown error'
            ]);

            return ['success' => false, 'error' => 'Failed to generate queries: ' . ($response['error'] ?? 'Unknown error')];
        } catch (\Exception $e) {
            Log::error('Exception in enhanced query generation', [
                'error' => $e->getMessage(),
                'question' => $question
            ]);
            
            return ['success' => false, 'error' => 'Query generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Execute queries with performance monitoring
     */
    public function executeQueriesWithMetrics(array $queries): array
    {
        $results = [];
        
        foreach ($queries as $query) {
            $startTime = microtime(true);
            
            try {
                $queryResult = DB::select($query['sql']);
                $executionTime = microtime(true) - $startTime;
                
                $results[$query['name']] = [
                    'description' => $query['description'],
                    'sql' => $query['sql'],
                    'data' => $queryResult,
                    'count' => count($queryResult),
                    'execution_time' => round($executionTime * 1000, 2), // ms
                    'query_type' => $query['query_type'] ?? 'unknown',
                    'complexity' => $query['complexity'] ?? 'unknown',
                    'data_quality' => $this->assessDataQuality($queryResult)
                ];
                
            } catch (\Exception $e) {
                $results[$query['name']] = [
                    'error' => $e->getMessage(),
                    'sql' => $query['sql'],
                    'description' => $query['description'],
                    'execution_time' => round((microtime(true) - $startTime) * 1000, 2)
                ];
            }
        }
        
        return $results;
    }

    /**
     * Generate insights with statistical analysis
     */
    public function generateAdvancedInsights(string $question, array $results): array
    {
        $prompt = "You are a friendly congressional data analyst. Provide a simple, easy-to-understand summary.

QUESTION: {$question}

QUERY RESULTS:
";

        foreach ($results as $queryName => $result) {
            $prompt .= "\n## {$queryName}\n";
            $prompt .= "Description: {$result['description']}\n";
            $prompt .= "Record Count: " . ($result['count'] ?? 0) . "\n";
            
            if (isset($result['data']) && !empty($result['data'])) {
                $prompt .= "Sample Data:\n";
                $sampleData = array_slice($result['data'], 0, 8);
                foreach ($sampleData as $row) {
                    $prompt .= json_encode($row) . "\n";
                }
            }
        }

        $prompt .= "

INSTRUCTIONS:
1. Write in simple, conversational language that anyone can understand
2. Start with a brief summary of what you found
3. List the key findings with specific numbers and names
4. If there are bills mentioned, format them as: **[Bill Type] [Number]: [Title]** (we'll add links later)
5. When you have congress_id data, you can reference specific bills by their type and number
5. Use bullet points and short paragraphs for easy reading
6. Avoid technical jargon, statistics terminology, or complex analysis
7. Focus on the most interesting and relevant findings
8. Don't mention SQL, databases, execution times, or technical details
9. Keep it concise and engaging

Write a friendly, informative response:";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        return [
            'insights' => $response['response'] ?? 'Advanced insights not available',
            'insights_html' => $response['response_html'] ?? '',
            'analysis_metadata' => [
                'queries_executed' => count($results),
                'total_records' => array_sum(array_column($results, 'count')),
                'avg_execution_time' => $this->calculateAverageExecutionTime($results),
                'data_quality_score' => $this->calculateOverallDataQuality($results)
            ]
        ];
    }

    private function buildConversationContext(array $history): string
    {
        if (empty($history)) {
            return "No previous conversation context.";
        }

        $context = "Previous conversation:\n";
        foreach (array_slice($history, -3) as $exchange) {
            $context .= "Q: " . $exchange['question'] . "\n";
            $context .= "A: " . substr($exchange['response'], 0, 200) . "...\n\n";
        }

        return $context;
    }

    private function getEnhancedSchema(): string
    {
        return "
# Enhanced Congressional Database Schema

## Core Tables with Performance Notes

### bills (Primary table - ~50K+ records)
- id (primary key, indexed)
- congress_id (unique, indexed - e.g., '119-s254')
- congress (integer, indexed - e.g., 119)
- number (indexed - e.g., '254')
- type (indexed - 'S', 'HR', 'HJRES', 'HRES', 'SJRES', 'SRES', 'HCONRES', 'SCONRES')
- origin_chamber ('House' or 'Senate', indexed)
- origin_chamber_code ('H' or 'S')
- title (text, full-text searchable)
- short_title (text, nullable)
- policy_area (text, indexed, nullable - common values: 'Defense', 'Healthcare', 'Transportation', etc.)
- introduced_date (date, indexed, nullable)
- update_date (timestamp, nullable)
- latest_action_date (date, indexed, nullable)
- latest_action_text (text, nullable)
- cosponsors_count (integer, indexed, default 0)
- bill_text (longtext, full-text searchable, nullable)
- ai_summary (longtext, nullable)
- created_at, updated_at (timestamps, indexed)

### members (Congressional members - ~600 records)
- id (primary key)
- bioguide_id (unique string, indexed)
- first_name, last_name, full_name (indexed for searches)
- party_abbreviation ('D', 'R', 'I', etc., indexed)
- party_name (full party name)
- state (2-letter state code, indexed, nullable)
- district (string, nullable)
- chamber ('house' or 'senate', indexed, nullable)
- current_member (boolean, indexed, default true)
- sponsored_legislation_count (integer, indexed, default 0)
- cosponsored_legislation_count (integer, indexed, default 0)

### bill_sponsors (Bill sponsorship - ~50K+ records)
- id (primary key)
- bill_id (foreign key to bills, indexed)
- bioguide_id (string, indexed, nullable)
- party ('D', 'R', 'I', etc., indexed, nullable)
- state (2-letter code, indexed, nullable)
- district (string, nullable)

### bill_cosponsors (Bill cosponsorship - ~500K+ records)
- id (primary key)
- bill_id (foreign key to bills, indexed)
- bioguide_id (string, indexed, nullable)
- party ('D', 'R', 'I', etc., indexed, nullable)
- state (2-letter code, indexed, nullable)
- sponsorship_date (date, indexed, nullable)
- is_original_cosponsor (boolean, indexed, default false)

### bill_actions (Legislative actions - ~1M+ records)
- id (primary key)
- bill_id (foreign key to bills, indexed)
- action_date (date, indexed)
- text (action description, searchable)
- type (string, indexed, nullable)

### bill_subjects (Bill topics/subjects - ~200K+ records)
- id (primary key)
- bill_id (foreign key to bills, indexed)
- name (subject name, indexed)
- type ('legislative' or 'policy_area', indexed, default 'legislative')

## Performance Optimization Notes
- Use LIMIT for large result sets
- Index on date fields for temporal queries
- Use strftime() for SQLite date formatting
- Consider using CTEs for complex multi-step analysis
- Party analysis: Use party_abbreviation for faster queries
- State analysis: Join members table for geographic analysis
- Temporal analysis: Use introduced_date, action_date for trends

## Common Query Patterns
- Bill trends: GROUP BY strftime('%Y-%m', introduced_date)
- Party analysis: JOIN with bill_sponsors/bill_cosponsors on party field
- State representation: JOIN members table on bioguide_id
- Bipartisan analysis: COUNT DISTINCT party in cosponsors
- Activity analysis: Use COUNT with appropriate GROUP BY
- Top/Most queries: ORDER BY with LIMIT

## Statistical Functions Available
- COUNT(), SUM(), AVG(), MIN(), MAX()
- Window functions: ROW_NUMBER(), RANK(), LAG(), LEAD()
- Date functions: strftime(), date(), datetime()
- String functions: LIKE, SUBSTR(), LENGTH()
";
    }

    private function parseEnhancedQueryResponse(string $response): array
    {
        Log::info('Parsing enhanced query response', [
            'response_length' => strlen($response),
            'response_preview' => substr($response, 0, 500)
        ]);

        // Try multiple JSON extraction methods
        $jsonStr = null;
        
        // Method 1: Look for complete JSON block
        if (preg_match('/\{[\s\S]*\}/s', $response, $matches)) {
            $jsonStr = $matches[0];
        }
        
        // Method 2: Look for JSON between code blocks
        if (!$jsonStr && preg_match('/```json\s*(\{[\s\S]*?\})\s*```/s', $response, $matches)) {
            $jsonStr = $matches[1];
        }
        
        // Method 3: Look for JSON after "queries"
        if (!$jsonStr && preg_match('/queries["\']?\s*:\s*\[[\s\S]*?\]/s', $response, $matches)) {
            $jsonStr = '{"queries":' . substr($matches[0], strpos($matches[0], '[')) . '}';
        }
        
        if ($jsonStr) {
            // Clean up common JSON issues
            $jsonStr = trim($jsonStr);
            $jsonStr = preg_replace('/,\s*}/', '}', $jsonStr); // Remove trailing commas
            $jsonStr = preg_replace('/,\s*]/', ']', $jsonStr); // Remove trailing commas in arrays
            
            Log::info('Attempting to parse JSON', [
                'json_preview' => substr($jsonStr, 0, 300)
            ]);
            
            $queryData = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($queryData['queries'])) {
                Log::info('Successfully parsed enhanced query response', [
                    'queries_count' => count($queryData['queries'])
                ]);
                
                return [
                    'success' => true,
                    'queries' => $queryData['queries'],
                    'analysis_approach' => $queryData['analysis_approach'] ?? '',
                    'context_considerations' => $queryData['context_considerations'] ?? ''
                ];
            } else {
                Log::warning('JSON decode failed', [
                    'json_error' => json_last_error_msg(),
                    'json_preview' => substr($jsonStr, 0, 500)
                ]);
            }
        }

        // If all parsing fails, try to extract queries manually
        Log::warning('All JSON parsing methods failed, attempting manual extraction');
        
        return $this->extractQueriesManually($response);
    }

    /**
     * Fallback method to extract queries manually from response
     */
    private function extractQueriesManually(string $response): array
    {
        $queries = [];
        
        // Look for SQL SELECT statements
        if (preg_match_all('/SELECT[\s\S]*?(?=SELECT|$)/i', $response, $matches)) {
            foreach ($matches[0] as $i => $sqlMatch) {
                $sql = trim($sqlMatch);
                if (strlen($sql) > 10) { // Basic validation
                    $queries[] = [
                        'name' => 'query_' . ($i + 1),
                        'description' => 'Extracted query for analysis',
                        'sql' => $sql,
                        'query_type' => 'extracted',
                        'complexity' => 'moderate'
                    ];
                }
            }
        }
        
        if (!empty($queries)) {
            Log::info('Successfully extracted queries manually', [
                'queries_count' => count($queries)
            ]);
            
            return [
                'success' => true,
                'queries' => $queries,
                'analysis_approach' => 'Manual query extraction used',
                'context_considerations' => 'Fallback parsing method applied'
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to parse enhanced query response'];
    }

    /**
     * Generate fast queries using predefined patterns
     */
    private function generateFastQuery(string $question): array
    {
        $question = strtolower($question);
        $queries = [];

        // Pattern matching for common questions
        if (str_contains($question, 'trending') || str_contains($question, 'popular')) {
            $queries[] = [
                'name' => 'trending_topics',
                'description' => 'Most popular policy areas by bill count',
                'sql' => "SELECT policy_area, COUNT(*) as bill_count FROM bills WHERE introduced_date >= date('now', '-12 months') AND policy_area IS NOT NULL GROUP BY policy_area ORDER BY bill_count DESC LIMIT 15",
                'query_type' => 'statistical',
                'complexity' => 'simple'
            ];
        } elseif (str_contains($question, 'china') || str_contains($question, 'chinese')) {
            $queries[] = [
                'name' => 'china_bills',
                'description' => 'Bills related to China',
                'sql' => "SELECT congress_id, type, number, title, introduced_date, policy_area FROM bills WHERE (title LIKE '%China%' OR title LIKE '%Chinese%' OR bill_text LIKE '%China%') AND introduced_date >= date('now', '-24 months') ORDER BY introduced_date DESC LIMIT 20",
                'query_type' => 'search',
                'complexity' => 'simple'
            ];
        } elseif (str_contains($question, 'republican') && str_contains($question, 'state')) {
            $queries[] = [
                'name' => 'republicans_by_state',
                'description' => 'Republican representatives by state',
                'sql' => "SELECT state, COUNT(*) as count FROM members WHERE party_abbreviation = 'R' AND current_member = 1 GROUP BY state ORDER BY count DESC LIMIT 20",
                'query_type' => 'statistical',
                'complexity' => 'simple'
            ];
        } elseif (str_contains($question, 'democrat') && str_contains($question, 'state')) {
            $queries[] = [
                'name' => 'democrats_by_state',
                'description' => 'Democratic representatives by state',
                'sql' => "SELECT state, COUNT(*) as count FROM members WHERE party_abbreviation = 'D' AND current_member = 1 GROUP BY state ORDER BY count DESC LIMIT 20",
                'query_type' => 'statistical',
                'complexity' => 'simple'
            ];
        } elseif (str_contains($question, 'how many') && str_contains($question, 'bill')) {
            $queries[] = [
                'name' => 'bill_counts',
                'description' => 'Bill counts by type and timeframe',
                'sql' => "SELECT type, COUNT(*) as count FROM bills WHERE introduced_date >= date('now', '-12 months') GROUP BY type ORDER BY count DESC",
                'query_type' => 'statistical',
                'complexity' => 'simple'
            ];
        } elseif (str_contains($question, 'healthcare') || str_contains($question, 'health')) {
            $queries[] = [
                'name' => 'healthcare_bills',
                'description' => 'Recent healthcare-related bills',
                'sql' => "SELECT congress_id, type, number, title, introduced_date FROM bills WHERE (policy_area LIKE '%Health%' OR title LIKE '%health%' OR title LIKE '%medical%') AND introduced_date >= date('now', '-12 months') ORDER BY introduced_date DESC LIMIT 15",
                'query_type' => 'search',
                'complexity' => 'simple'
            ];
        } elseif (str_contains($question, 'defense') || str_contains($question, 'military')) {
            $queries[] = [
                'name' => 'defense_bills',
                'description' => 'Recent defense and military bills',
                'sql' => "SELECT congress_id, type, number, title, introduced_date FROM bills WHERE (policy_area LIKE '%Defense%' OR title LIKE '%defense%' OR title LIKE '%military%') AND introduced_date >= date('now', '-12 months') ORDER BY introduced_date DESC LIMIT 15",
                'query_type' => 'search',
                'complexity' => 'simple'
            ];
        } else {
            // Default general query
            $queries[] = [
                'name' => 'general_stats',
                'description' => 'General congressional statistics',
                'sql' => "SELECT 'Total Bills' as metric, COUNT(*) as value FROM bills UNION ALL SELECT 'Current Members', COUNT(*) FROM members WHERE current_member = 1",
                'query_type' => 'statistical',
                'complexity' => 'simple'
            ];
        }

        return [
            'success' => true,
            'queries' => $queries,
            'analysis_approach' => 'Fast pattern-based query generation',
            'context_considerations' => 'Using optimized predefined queries for speed'
        ];
    }

    private function assessDataQuality(array $data): array
    {
        if (empty($data)) {
            return ['score' => 0, 'issues' => ['No data returned']];
        }

        $issues = [];
        $score = 100;

        // Check for null values
        $nullCount = 0;
        $totalFields = 0;
        
        foreach ($data as $row) {
            foreach ($row as $field => $value) {
                $totalFields++;
                if (is_null($value) || $value === '') {
                    $nullCount++;
                }
            }
        }

        if ($totalFields > 0) {
            $nullPercentage = ($nullCount / $totalFields) * 100;
            if ($nullPercentage > 20) {
                $issues[] = "High null/empty values: {$nullPercentage}%";
                $score -= 20;
            }
        }

        return [
            'score' => max(0, $score),
            'issues' => $issues,
            'null_percentage' => $nullPercentage ?? 0
        ];
    }

    private function calculateStatistics(array $data): array
    {
        if (empty($data)) return [];

        $stats = [];
        $firstRow = $data[0];

        foreach ($firstRow as $field => $value) {
            if (is_numeric($value)) {
                $values = array_column($data, $field);
                $values = array_filter($values, 'is_numeric');
                
                if (!empty($values)) {
                    $stats[$field] = [
                        'count' => count($values),
                        'sum' => array_sum($values),
                        'avg' => round(array_sum($values) / count($values), 2),
                        'min' => min($values),
                        'max' => max($values)
                    ];
                }
            }
        }

        return $stats;
    }

    private function calculateAverageExecutionTime(array $results): float
    {
        $times = array_column($results, 'execution_time');
        $times = array_filter($times, 'is_numeric');
        
        return !empty($times) ? round(array_sum($times) / count($times), 2) : 0;
    }

    private function calculateOverallDataQuality(array $results): float
    {
        $scores = [];
        foreach ($results as $result) {
            if (isset($result['data_quality']['score'])) {
                $scores[] = $result['data_quality']['score'];
            }
        }

        return !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
    }
}