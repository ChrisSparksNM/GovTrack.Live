<?php

namespace App\Http\Controllers;

use App\Services\CongressChatbotService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ChatbotController extends Controller
{
    private CongressChatbotService $chatbotService;

    public function __construct(CongressChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * Show the chatbot interface
     */
    public function index(): View
    {
        return view('chatbot.index');
    }

    /**
     * Process a chat message and return AI response
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'conversation_id' => 'nullable|string',
        ]);

        $message = $request->input('message');
        $conversationId = $request->input('conversation_id', uniqid());

        // Get conversation context from session if available
        $context = session()->get("chatbot_context_{$conversationId}", []);

        $response = $this->chatbotService->askQuestion($message, $context);

        if ($response['success']) {
            // Store conversation context
            $context[] = [
                'question' => $message,
                'response' => $response['response'],
                'timestamp' => now()->toISOString()
            ];
            
            // Keep only last 5 exchanges to manage context size
            $context = array_slice($context, -5);
            session()->put("chatbot_context_{$conversationId}", $context);

            // Get the raw response and clean it
            $rawResponse = $response['response_html'] ?? $response['response'];
            $cleanResponse = $this->cleanResponse($rawResponse);
            
            // Format for better readability but keep it simple for typewriter effect
            $formattedResponse = $this->formatSimpleResponse($cleanResponse);
            
            // Add bill links to the formatted response (this adds HTML links)
            $linkedResponse = $this->addBillLinks($formattedResponse);

            return response()->json([
                'success' => true,
                'response' => $linkedResponse,
                'data_sources' => $this->cleanDataSources($response['data_sources'] ?? []),
                'conversation_id' => $conversationId,
                'analysis_metadata' => $this->cleanAnalysisMetadata($response['analysis_metadata'] ?? []),
                'performance_info' => $this->extractPerformanceInfo($response)
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $response['error'] ?? 'Failed to process your question'
        ], 500);
    }

    /**
     * Clean response to remove SQL-related mentions
     */
    private function cleanResponse(string $response): string
    {
        // Remove SQL-related terms and technical details
        $patterns = [
            '/\b(SQL|query|queries|database|table|JOIN|SELECT|WHERE|FROM|GROUP BY|ORDER BY)\b/i' => '',
            '/Generated \d+ optimized queries?/i' => '',
            '/Query Results Summary:.*?🤖/s' => '🤖',
            '/📝 Generated.*?queries?:.*?(?=📊|🤖|$)/s' => '',
            '/Based on the database query results?/i' => 'Based on the congressional data analysis',
            '/querying the database/i' => 'analyzing the data',
            '/database analysis/i' => 'data analysis',
            '/query results/i' => 'analysis results',
            '/from the query/i' => 'from the analysis',
            '/database shows/i' => 'data shows',
            '/according to the database/i' => 'according to the data',
            '/database indicates/i' => 'data indicates',
            '/database reveals/i' => 'analysis reveals',
            '/SQL analysis/i' => 'data analysis',
            '/database search/i' => 'data search',
            '/queried for/i' => 'analyzed for',
            '/database contains/i' => 'data contains',
            '/from our database/i' => 'from our analysis',
            '/the database/i' => 'the data',
            '/Database:/i' => 'Analysis:',
            '/Query:/i' => 'Analysis:',
        ];
        
        $cleanResponse = $response;
        foreach ($patterns as $pattern => $replacement) {
            $cleanResponse = preg_replace($pattern, $replacement, $cleanResponse);
        }
        
        // Clean up extra whitespace and multiple spaces
        $cleanResponse = preg_replace('/\s+/', ' ', $cleanResponse);
        $cleanResponse = preg_replace('/\s*\n\s*/', "\n", $cleanResponse);
        $cleanResponse = trim($cleanResponse);
        
        return $cleanResponse;
    }

    /**
     * Clean data sources to remove SQL mentions
     */
    private function cleanDataSources(array $sources): array
    {
        return array_map(function($source) {
            $patterns = [
                '/\b(SQL|query|queries|database|table)\b/i' => 'analysis',
                '/database search/i' => 'data search',
                '/queried/i' => 'analyzed',
                '/from database/i' => 'from data analysis',
                '/database results/i' => 'analysis results',
            ];
            
            $cleanSource = $source;
            foreach ($patterns as $pattern => $replacement) {
                $cleanSource = preg_replace($pattern, $replacement, $cleanSource);
            }
            
            return $cleanSource;
        }, $sources);
    }

    /**
     * Get suggested questions to help users get started
     */
    public function suggestions(): JsonResponse
    {
        $suggestions = [
            'General Statistics' => [
                'How many bills are currently in Congress?',
                'What are the most popular policy areas this year?',
                'Show me the party breakdown in Congress'
            ],
            'Recent Activity' => [
                'What bills were introduced recently?',
                'Who are the most active bill sponsors lately?',
                'What are the trending topics in Congress?'
            ],
            'Topic-Based Analysis' => [
                'Tell me about bills related to China',
                'What healthcare bills have been introduced recently?',
                'Show me climate and energy legislation',
                'What bills mention Donald Trump or Joe Biden?'
            ],
            'Specific Analysis' => [
                'Which states have the most Republican representatives?',
                'Show me bipartisan legislation examples',
                'What defense and military bills are active?'
            ],
            'Member Information' => [
                'Who represents California in the Senate?',
                'Which members have sponsored the most bills?',
                'Show me new members of Congress'
            ]
        ];

        return response()->json($suggestions);
    }

    /**
     * Clear conversation history
     */
    public function clearConversation(Request $request): JsonResponse
    {
        $conversationId = $request->input('conversation_id');
        
        if ($conversationId) {
            session()->forget("chatbot_context_{$conversationId}");
        }

        return response()->json(['success' => true]);
    }

    /**
     * Clean analysis metadata to remove technical details
     */
    private function cleanAnalysisMetadata(array $metadata): array
    {
        $cleaned = [];
        
        if (isset($metadata['queries_executed'])) {
            $cleaned['analyses_performed'] = $metadata['queries_executed'];
        }
        
        if (isset($metadata['total_records'])) {
            $cleaned['data_points_analyzed'] = $metadata['total_records'];
        }
        
        if (isset($metadata['data_quality_score'])) {
            $cleaned['data_confidence'] = $this->formatDataQuality($metadata['data_quality_score']);
        }
        
        return $cleaned;
    }

    /**
     * Extract performance information for user display
     */
    private function extractPerformanceInfo(array $response): array
    {
        $info = [];
        
        if (isset($response['analysis_metadata']['avg_execution_time'])) {
            $time = $response['analysis_metadata']['avg_execution_time'];
            if ($time < 100) {
                $info['response_speed'] = 'Very Fast';
            } elseif ($time < 500) {
                $info['response_speed'] = 'Fast';
            } else {
                $info['response_speed'] = 'Detailed Analysis';
            }
        }
        
        if (isset($response['analysis_approach'])) {
            $info['analysis_type'] = 'Advanced Statistical Analysis';
        }
        
        return $info;
    }

    /**
     * Format data quality score for user display
     */
    private function formatDataQuality(float $score): string
    {
        if ($score >= 90) return 'Very High';
        if ($score >= 75) return 'High';
        if ($score >= 60) return 'Good';
        if ($score >= 40) return 'Fair';
        return 'Limited';
    }

    /**
     * Add links to bill references in the response
     */
    private function addBillLinks(string $response): string
    {
        // Pattern 1: Handle natural language bill references like "Senate Bill 2960", "House Resolution 444"
        $naturalPattern = '/\b(Senate|House)\s+(Bill|Resolution|Concurrent\s+Resolution)\s+(\d+)/i';
        
        $linkedResponse = preg_replace_callback($naturalPattern, function ($matches) {
            $chamber = strtolower($matches[1]); // "senate" or "house"
            $billType = strtolower($matches[2]); // "bill", "resolution", etc.
            $number = $matches[3];
            
            // Map natural language to database types
            $type = null;
            if ($chamber === 'senate') {
                if (str_contains($billType, 'concurrent')) {
                    $type = 'SCONRES';
                } elseif ($billType === 'resolution') {
                    $type = 'SRES';
                } else {
                    $type = 'S';
                }
            } else { // house
                if (str_contains($billType, 'concurrent')) {
                    $type = 'HCONRES';
                } elseif ($billType === 'resolution') {
                    $type = 'HRES';
                } else {
                    $type = 'HR';
                }
            }
            
            // Try to find the bill in the database
            $bill = \App\Models\Bill::where('type', $type)
                ->where('number', $number)
                ->first();
            
            if ($bill) {
                $url = route('bills.show', $bill->congress_id);
                return "<a href=\"{$url}\" class=\"text-blue-600 hover:text-blue-800 underline\" target=\"_blank\">{$matches[0]}</a>";
            }
            
            return $matches[0];
        }, $response);
        
        // Pattern 2: Handle bill references with titles in bold format: **HR 1234: Title**
        $pattern = '/\*\*([HS]\.?R?\.?\s*\d+):\s*([^*]+)\*\*/i';
        
        $linkedResponse = preg_replace_callback($pattern, function ($matches) {
            $billRef = trim($matches[1]);
            $title = trim($matches[2]);
            
            // Normalize bill reference
            $billRef = preg_replace('/\s+/', ' ', $billRef);
            $billRef = str_replace('.', '', $billRef);
            
            // Extract type and number
            if (preg_match('/^([HS])R?\s*(\d+)$/i', $billRef, $billMatches)) {
                $type = strtoupper($billMatches[1]) === 'H' ? 'HR' : 'S';
                $number = $billMatches[2];
                
                // Try to find the bill in the database
                $bill = \App\Models\Bill::where('type', $type)
                    ->where('number', $number)
                    ->first();
                
                if ($bill) {
                    $url = route('bills.show', $bill->congress_id);
                    return "**<a href=\"{$url}\" class=\"text-blue-600 hover:text-blue-800 underline\" target=\"_blank\">{$type} {$number}: {$title}</a>**";
                }
            }
            
            // If bill not found, return original format without link
            return "**{$billRef}: {$title}**";
        }, $linkedResponse);
        
        // Pattern 3: Handle simple bill references like "HR 1234", "S 567" (but avoid those already inside links)
        $simplePattern = '/(?<!href=")(?<!target="_blank">)\b([HS]\.?R?\.?\s*\d+)\b(?![^<]*<\/a>)/i';
        $linkedResponse = preg_replace_callback($simplePattern, function ($matches) {
            $billRef = trim($matches[1]);
            $originalBillRef = $billRef; // Keep original for display
            
            // Normalize for database lookup
            $billRef = preg_replace('/\s+/', ' ', $billRef);
            $billRef = str_replace('.', '', $billRef);
            
            if (preg_match('/^([HS])R?\s*(\d+)$/i', $billRef, $billMatches)) {
                $type = strtoupper($billMatches[1]) === 'H' ? 'HR' : 'S';
                $number = $billMatches[2];
                
                $bill = \App\Models\Bill::where('type', $type)
                    ->where('number', $number)
                    ->first();
                
                if ($bill) {
                    $url = route('bills.show', $bill->congress_id);
                    return "<a href=\"{$url}\" class=\"text-blue-600 hover:text-blue-800 underline\" target=\"_blank\">{$originalBillRef}</a>";
                }
            }
            
            return $matches[0];
        }, $linkedResponse);
        
        return $linkedResponse;
    }

    /**
     * Format response with simple, readable structure that works with typewriter effect
     */
    private function formatSimpleResponse(string $response): string
    {
        // Normalize line breaks
        $text = preg_replace('/\r\n|\r/', "\n", $response);
        
        // More aggressive paragraph detection patterns
        $patterns = [
            // Headers that end with colon (with more context)
            '/([.!?])\s+([A-Z][A-Za-z\s,&-]+:)/' => '$1' . "\n\n" . '$2',
            // Common section starters
            '/([.!?])\s+(Recently[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Data[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Based\s+on[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Here\s+are[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(The\s+most[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Notable[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Key[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Summary[^.]*[A-Z])/' => '$1' . "\n\n" . '$2',
            // Break on numbered lists
            '/([.!?])\s+(\d+\.\s+[A-Z])/' => '$1' . "\n\n" . '$2',
            // Break on bullet points
            '/([.!?])\s+([-•*]\s+[A-Z])/' => '$1' . "\n\n" . '$2',
            // Break on "However," "Additionally," etc.
            '/([.!?])\s+(However,\s+[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Additionally,\s+[A-Z])/' => '$1' . "\n\n" . '$2',
            '/([.!?])\s+(Furthermore,\s+[A-Z])/' => '$1' . "\n\n" . '$2',
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }
        
        // Also try to break on very long sentences (over 200 chars)
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        $rebuiltText = [];
        $currentParagraph = '';
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (empty($sentence)) continue;
            
            // If current paragraph is getting too long, start a new one
            if (strlen($currentParagraph) > 200 && !empty($currentParagraph)) {
                $rebuiltText[] = $currentParagraph;
                $currentParagraph = $sentence;
            } else {
                $currentParagraph .= (empty($currentParagraph) ? '' : ' ') . $sentence;
            }
        }
        
        if (!empty($currentParagraph)) {
            $rebuiltText[] = $currentParagraph;
        }
        
        // Join with double line breaks for proper paragraph separation
        $text = implode("\n\n", $rebuiltText);
        
        // Split into paragraphs on double line breaks
        $paragraphs = preg_split('/\n\s*\n+/', $text);
        $formattedParagraphs = [];
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (empty($paragraph)) continue;
            
            // Format this paragraph
            $formatted = $this->formatParagraph($paragraph);
            if (!empty($formatted)) {
                $formattedParagraphs[] = $formatted;
            }
        }
        
        return implode("\n\n", $formattedParagraphs);
    }

    /**
     * Format a single paragraph with basic structure
     */
    private function formatParagraph(string $paragraph): string
    {
        // Check if it contains bullet points
        if (preg_match('/^[-*•]\s+/m', $paragraph)) {
            return $this->formatBulletList($paragraph);
        }
        
        // Check if it contains numbered list
        if (preg_match('/^\d+\.\s+/m', $paragraph)) {
            return $this->formatNumberedList($paragraph);
        }
        
        // Regular paragraph - just clean it up and apply inline formatting
        return $this->formatInlineElements($paragraph);
    }

    /**
     * Format bullet lists simply
     */
    private function formatBulletList(string $text): string
    {
        $lines = explode("\n", $text);
        $formatted = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // If it's a bullet point, format it
            if (preg_match('/^[-*•]\s+(.+)$/', $line, $matches)) {
                $formatted[] = "• " . trim($matches[1]);
            } else {
                $formatted[] = $line;
            }
        }
        
        return implode("\n", $formatted);
    }

    /**
     * Format numbered lists simply
     */
    private function formatNumberedList(string $text): string
    {
        $lines = explode("\n", $text);
        $formatted = [];
        $counter = 1;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // If it's a numbered item, format it
            if (preg_match('/^\d+\.\s+(.+)$/', $line, $matches)) {
                $formatted[] = "{$counter}. " . trim($matches[1]);
                $counter++;
            } else {
                $formatted[] = $line;
            }
        }
        
        return implode("\n", $formatted);
    }

    /**
     * Format inline elements like bold, italic, etc. - keep simple for typewriter effect
     */
    private function formatInlineElements(string $text): string
    {
        // Keep it simple but preserve important formatting markers
        // Bold text markers
        $text = preg_replace('/\*\*(.*?)\*\*/', '**$1**', $text);
        
        return $text;
    }
}