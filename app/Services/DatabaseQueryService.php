<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseQueryService
{
    private AnthropicService $anthropicService;
    
    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
    }

    /**
     * Generate and execute SQL queries based on user question
     */
    public function queryDatabase(string $question): array
    {
        try {
            // Get database schema
            $schema = $this->getDatabaseSchema();
            
            // Generate SQL queries using AI
            $queryResponse = $this->generateQueries($question, $schema);
            
            if (!$queryResponse['success']) {
                return $queryResponse;
            }
            
            // Execute the queries
            $results = $this->executeQueries($queryResponse['queries']);
            
            // Analyze results with AI
            $analysis = $this->analyzeResults($question, $results, $schema);
            
            return [
                'success' => true,
                'queries' => $queryResponse['queries'],
                'results' => $results,
                'analysis' => $analysis['analysis'] ?? 'Analysis not available',
                'analysis_html' => $analysis['analysis_html'] ?? '',
                'data_sources' => $this->generateDataSources($queryResponse['queries'])
            ];
            
        } catch (\Exception $e) {
            Log::error('Database query service error', [
                'question' => $question,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to query database: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get comprehensive database schema
     */
    private function getDatabaseSchema(): string
    {
        return "
# Congressional Database Schema

## Core Tables

### bills
- id (primary key)
- congress_id (unique, e.g., '119-s254')
- congress (integer, e.g., 119)
- number (e.g., '254')
- type (e.g., 'S', 'HR', 'HJRES', 'HRES', 'SJRES', 'SRES', 'HCONRES', 'SCONRES')
- origin_chamber ('House' or 'Senate')
- origin_chamber_code ('H' or 'S')
- title (text)
- short_title (text, nullable)
- policy_area (text, nullable)
- introduced_date (date, nullable)
- update_date (timestamp, nullable)
- update_date_including_text (timestamp, nullable)
- latest_action_date (date, nullable)
- latest_action_time (time, nullable)
- latest_action_text (text, nullable)
- api_url (string, nullable)
- legislation_url (string, nullable)
- actions_count (integer, default 0)
- summaries_count (integer, default 0)
- subjects_count (integer, default 0)
- cosponsors_count (integer, default 0)
- text_versions_count (integer, default 0)
- committees_count (integer, default 0)
- bill_text (longtext, nullable)
- bill_text_version_type (string, nullable)
- bill_text_date (timestamp, nullable)
- bill_text_source_url (string, nullable)
- ai_summary (longtext, nullable)
- ai_summary_html (longtext, nullable)
- ai_summary_generated_at (timestamp, nullable)
- ai_summary_metadata (json, nullable)
- is_fully_scraped (boolean, default false)
- last_scraped_at (timestamp, nullable)
- scraping_errors (json, nullable)
- created_at, updated_at

### members
- id (primary key)
- bioguide_id (unique string)
- first_name, last_name, full_name
- direct_order_name, inverted_order_name
- honorific_name (nullable)
- party_abbreviation ('D', 'R', 'I', etc.)
- party_name (full party name)
- state (2-letter state code, nullable)
- district (string, nullable)
- chamber ('house' or 'senate', nullable)
- birth_year (string, nullable)
- current_member (boolean, default true)
- image_url, image_attribution (nullable)
- official_website_url (nullable)
- office_address, office_city, office_phone, office_zip_code (nullable)
- sponsored_legislation_count (integer, default 0)
- cosponsored_legislation_count (integer, default 0)
- party_history (json, nullable)
- previous_names (json, nullable)
- last_updated_at (timestamp, nullable)
- created_at, updated_at

### bill_sponsors
- id (primary key)
- bill_id (foreign key to bills)
- bioguide_id (string, nullable)
- first_name, last_name, full_name
- party ('D', 'R', 'I', etc., nullable)
- state (2-letter code, nullable)
- district (string, nullable)
- is_by_request ('Y'/'N', nullable)
- created_at, updated_at

### bill_cosponsors
- id (primary key)
- bill_id (foreign key to bills)
- bioguide_id (string, nullable)
- first_name, last_name, full_name
- party ('D', 'R', 'I', etc., nullable)
- state (2-letter code, nullable)
- district (string, nullable)
- sponsorship_date (date, nullable)
- is_original_cosponsor (boolean, default false)
- sponsorship_withdrawn_date (date, nullable)
- created_at, updated_at

### bill_actions
- id (primary key)
- bill_id (foreign key to bills)
- action_date (date)
- action_time (time, nullable)
- text (action description)
- type (string, nullable)
- action_code (string, nullable)
- source_system (string, nullable)
- committees (json, nullable)
- recorded_votes (json, nullable)
- created_at, updated_at

### bill_summaries
- id (primary key)
- bill_id (foreign key to bills)
- action_date (date, nullable)
- action_desc (string, nullable)
- text (longtext, nullable)
- update_date (timestamp, nullable)
- version_code (string, nullable)
- created_at, updated_at

### bill_subjects
- id (primary key)
- bill_id (foreign key to bills)
- name (subject name)
- type ('legislative' or 'policy_area', default 'legislative')
- created_at, updated_at

## Relationships
- bills.id -> bill_sponsors.bill_id (one-to-many)
- bills.id -> bill_cosponsors.bill_id (one-to-many)
- bills.id -> bill_actions.bill_id (one-to-many)
- bills.id -> bill_summaries.bill_id (one-to-many)
- bills.id -> bill_subjects.bill_id (one-to-many)
- members.bioguide_id -> bill_sponsors.bioguide_id (one-to-many)
- members.bioguide_id -> bill_cosponsors.bioguide_id (one-to-many)

## Key Indexes
- bills: congress_id (unique), congress+type, introduced_date, policy_area
- members: bioguide_id (unique), party_abbreviation, state, current_member, chamber
- bill_sponsors: bill_id, bioguide_id, party+state
- bill_cosponsors: bill_id, bioguide_id, party+state, sponsorship_date
";
    }

    /**
     * Generate SQL queries using AI
     */
    private function generateQueries(string $question, string $schema): array
    {
        // Pre-process the question to identify state references
        $stateInfo = $this->identifyStateReferences($question);
        
        $prompt = "You are a SQL expert analyzing a congressional database. Generate appropriate SQL queries to answer the user's question.

DATABASE SCHEMA:
{$schema}

USER QUESTION: {$question}

" . ($stateInfo ? "STATE CONTEXT: The user is asking about {$stateInfo['state_name']} (state code: {$stateInfo['state_code']}). Make sure to filter by state = '{$stateInfo['state_code']}' in your queries.\n\n" : "") . "

INSTRUCTIONS:
1. Generate 1-3 SQL queries that will provide comprehensive data to answer the question
2. Use proper JOINs when needed to get related data
3. Include appropriate WHERE clauses, GROUP BY, ORDER BY, and LIMIT clauses
4. For questions about 'most' or 'top', use ORDER BY with LIMIT
5. For state-based questions, JOIN bills with bill_sponsors/bill_cosponsors and filter by state
6. For party analysis, use party_abbreviation field
7. For bill content searches, use LIKE with wildcards on title, bill_text, or ai_summary
8. Return queries as a JSON array with descriptive names

IMPORTANT RULES FOR STATE QUERIES:
- To find bills from a specific state, JOIN bills with bill_sponsors table and filter by state
- Include both sponsored and cosponsored bills for comprehensive state representation
- Always include bill details (congress_id, title, type, number) in results
- Include sponsor/cosponsor names and party information
- Use proper state codes (e.g., 'NJ' for New Jersey, 'CA' for California)

IMPORTANT TECHNICAL RULES:
- Always use proper SQL syntax for SQLite
- Use strftime() for date formatting in SQLite, not DATE_FORMAT()
- Use LIMIT for top/most questions
- Include member names and details when relevant
- Group by appropriate fields for aggregation
- Use LEFT JOIN when you want to include records even if related data doesn't exist

RESPONSE FORMAT:
Return ONLY a valid JSON object with this exact structure (no additional text):
{
  \"queries\": [
    {
      \"name\": \"descriptive_name\",
      \"description\": \"What this query does\",
      \"sql\": \"SELECT ... FROM ... WHERE ...\"
    }
  ]
}

IMPORTANT: 
- Return ONLY the JSON, no explanatory text before or after
- Ensure all quotes in SQL are properly escaped
- Keep SQL on single lines or use proper JSON escaping for multiline
- Test that your JSON is valid before responding

Generate the queries now:";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        if (!$response['success']) {
            return [
                'success' => false,
                'error' => 'Failed to generate queries: ' . $response['error']
            ];
        }

        // Extract JSON from the response
        $responseText = $response['response'];
        
        // Try to find JSON in the response - look for the complete JSON block
        if (preg_match('/\{[\s\S]*\}/s', $responseText, $matches)) {
            $jsonStr = $matches[0];
            
            // Clean up the JSON string
            $jsonStr = trim($jsonStr);
            
            $queryData = json_decode($jsonStr, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($queryData['queries'])) {
                return [
                    'success' => true,
                    'queries' => $queryData['queries']
                ];
            } else {
                Log::warning('JSON decode error', [
                    'error' => json_last_error_msg(),
                    'json' => substr($jsonStr, 0, 500)
                ]);
            }
        }
        
        // If JSON parsing fails, try to extract queries manually
        Log::warning('Failed to parse JSON, attempting manual extraction', [
            'response' => substr($responseText, 0, 1000)
        ]);
        
        return [
            'success' => false,
            'error' => 'Failed to parse query response: ' . $responseText
        ];
    }

    /**
     * Execute the generated queries safely
     */
    private function executeQueries(array $queries): array
    {
        $results = [];
        
        foreach ($queries as $query) {
            try {
                // Basic SQL injection protection - only allow SELECT statements and CTEs
                $sql = trim($query['sql']);
                if (!preg_match('/^\s*(SELECT|WITH)\s+/i', $sql)) {
                    $results[$query['name']] = [
                        'error' => 'Only SELECT queries and CTEs are allowed',
                        'description' => $query['description']
                    ];
                    continue;
                }
                
                // Additional safety check - no dangerous keywords
                $dangerousKeywords = ['DROP', 'DELETE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 'TRUNCATE'];
                $upperSql = strtoupper($sql);
                foreach ($dangerousKeywords as $keyword) {
                    if (strpos($upperSql, $keyword) !== false) {
                        $results[$query['name']] = [
                            'error' => "Dangerous keyword '{$keyword}' not allowed",
                            'description' => $query['description']
                        ];
                        continue 2;
                    }
                }
                
                // Execute the query
                $queryResult = DB::select($sql);
                
                $results[$query['name']] = [
                    'description' => $query['description'],
                    'sql' => $sql,
                    'data' => $queryResult,
                    'count' => count($queryResult)
                ];
                
            } catch (\Exception $e) {
                $results[$query['name']] = [
                    'error' => $e->getMessage(),
                    'sql' => $query['sql'],
                    'description' => $query['description']
                ];
            }
        }
        
        return $results;
    }

    /**
     * Analyze query results using AI
     */
    private function analyzeResults(string $question, array $results, string $schema): array
    {
        $prompt = "You are a friendly congressional data analyst. Provide a simple, easy-to-understand summary of the data.

USER QUESTION: {$question}

QUERY RESULTS:
";

        foreach ($results as $queryName => $result) {
            $prompt .= "\n## {$queryName}\n";
            $prompt .= "Description: {$result['description']}\n";
            
            if (isset($result['error'])) {
                $prompt .= "Error: {$result['error']}\n";
            } else {
                $prompt .= "Count: {$result['count']} records\n";
                
                if ($result['count'] > 0) {
                    $prompt .= "Sample Data:\n";
                    $sampleData = array_slice($result['data'], 0, 10);
                    foreach ($sampleData as $row) {
                        $prompt .= json_encode($row) . "\n";
                    }
                }
            }
        }

        $prompt .= "

INSTRUCTIONS:
1. Write in simple, conversational language that anyone can understand
2. Start with a brief summary of what you found
3. List the key findings with specific numbers
4. If there are bills mentioned, format them as: **[Bill Type] [Number]: [Title]** (we'll add links later)
5. When you have congress_id data, you can reference specific bills by their type and number
5. Use bullet points for easy reading
6. Avoid technical jargon, statistics terminology, or complex analysis
7. Keep it concise and focused on the most interesting findings
8. Don't mention SQL, databases, or technical details

Write a friendly, informative response:";

        $response = $this->anthropicService->generateChatResponse($prompt);
        
        return [
            'analysis' => $response['response'] ?? 'Analysis not available',
            'analysis_html' => $response['response_html'] ?? ''
        ];
    }

    /**
     * Identify state references in the user's question
     */
    private function identifyStateReferences(string $question): ?array
    {
        $stateMap = [
            'alabama' => 'AL', 'alaska' => 'AK', 'arizona' => 'AZ', 'arkansas' => 'AR', 'california' => 'CA',
            'colorado' => 'CO', 'connecticut' => 'CT', 'delaware' => 'DE', 'florida' => 'FL', 'georgia' => 'GA',
            'hawaii' => 'HI', 'idaho' => 'ID', 'illinois' => 'IL', 'indiana' => 'IN', 'iowa' => 'IA',
            'kansas' => 'KS', 'kentucky' => 'KY', 'louisiana' => 'LA', 'maine' => 'ME', 'maryland' => 'MD',
            'massachusetts' => 'MA', 'michigan' => 'MI', 'minnesota' => 'MN', 'mississippi' => 'MS', 'missouri' => 'MO',
            'montana' => 'MT', 'nebraska' => 'NE', 'nevada' => 'NV', 'new hampshire' => 'NH', 'new jersey' => 'NJ',
            'new mexico' => 'NM', 'new york' => 'NY', 'north carolina' => 'NC', 'north dakota' => 'ND', 'ohio' => 'OH',
            'oklahoma' => 'OK', 'oregon' => 'OR', 'pennsylvania' => 'PA', 'rhode island' => 'RI', 'south carolina' => 'SC',
            'south dakota' => 'SD', 'tennessee' => 'TN', 'texas' => 'TX', 'utah' => 'UT', 'vermont' => 'VT',
            'virginia' => 'VA', 'washington' => 'WA', 'west virginia' => 'WV', 'wisconsin' => 'WI', 'wyoming' => 'WY'
        ];
        
        $questionLower = strtolower($question);
        
        // Check for full state names
        foreach ($stateMap as $stateName => $stateCode) {
            if (strpos($questionLower, $stateName) !== false) {
                return [
                    'state_name' => ucwords($stateName),
                    'state_code' => $stateCode
                ];
            }
        }
        
        // Check for state codes (e.g., "NJ", "CA")
        if (preg_match('/\b([A-Z]{2})\b/', $question, $matches)) {
            $stateCode = $matches[1];
            $stateName = array_search($stateCode, $stateMap);
            if ($stateName) {
                return [
                    'state_name' => ucwords($stateName),
                    'state_code' => $stateCode
                ];
            }
        }
        
        return null;
    }

    /**
     * Generate data sources list
     */
    private function generateDataSources(array $queries): array
    {
        $sources = [];
        foreach ($queries as $query) {
            $sources[] = $query['description'];
        }
        return $sources;
    }
}