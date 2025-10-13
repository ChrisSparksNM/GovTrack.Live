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

            // Always format the response for better readability
            $rawResponse = $response['response_html'] ?? $response['response'];
            
            // Format the response with proper HTML structure
            $formattedResponse = $this->formatPlainTextResponse($rawResponse);
            
            // Add bill links to the formatted response
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
     * Format plain text response into proper HTML with paragraphs and structure
     */
    private function formatPlainTextResponse(string $response): string
    {
        // Clean the response first
        $cleanResponse = $this->cleanResponse($response);
        
        // Handle different formatting patterns
        $formattedResponse = $this->processAdvancedFormatting($cleanResponse);
        
        return $formattedResponse;
    }

    /**
     * Process advanced formatting for better readability
     */
    private function processAdvancedFormatting(string $text): string
    {
        // First, normalize line breaks
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        
        // Split into sections based on various patterns
        $sections = $this->splitIntoSections($text);
        $formattedSections = [];
        
        foreach ($sections as $section) {
            $section = trim($section);
            if (empty($section)) continue;
            
            $formattedSection = $this->formatSection($section);
            if (!empty($formattedSection)) {
                $formattedSections[] = $formattedSection;
            }
        }
        
        return implode("\n\n", $formattedSections);
    }

    /**
     * Split text into logical sections
     */
    private function splitIntoSections(string $text): array
    {
        // Split on double line breaks, headers, or major formatting changes
        $sections = preg_split('/\n\s*\n+/', $text);
        
        // Further split sections that contain multiple concepts
        $refinedSections = [];
        foreach ($sections as $section) {
            // Split on sentences that end with periods followed by capital letters (new topics)
            $subSections = preg_split('/(?<=\.)\s+(?=[A-Z][^.]*:)|(?<=\.)\s+(?=\d+\.)|(?<=\.)\s+(?=[-*•])/m', $section);
            $refinedSections = array_merge($refinedSections, $subSections);
        }
        
        return $refinedSections;
    }

    /**
     * Format individual sections with appropriate HTML structure
     */
    private function formatSection(string $section): string
    {
        $section = trim($section);
        if (empty($section)) return '';
        
        // Check for headers (lines ending with colons or starting with numbers/bullets)
        if (preg_match('/^(.+):(?:\s*$|\s+(?=[A-Z]))/m', $section, $matches)) {
            return $this->formatHeaderSection($section);
        }
        
        // Check for numbered lists
        if (preg_match('/^\d+\.\s+/', $section)) {
            return $this->formatNumberedList($section);
        }
        
        // Check for bullet lists
        if (preg_match('/^[-*•]\s+/', $section)) {
            return $this->formatBulletList($section);
        }
        
        // Check for key-value pairs or statistics
        if (preg_match('/^[^:]+:\s*[^:]+$/m', $section) && substr_count($section, ':') > 1) {
            return $this->formatKeyValueSection($section);
        }
        
        // Regular paragraph
        return $this->formatParagraph($section);
    }

    /**
     * Format sections with headers
     */
    private function formatHeaderSection(string $section): string
    {
        $lines = explode("\n", $section);
        $formatted = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Check if it's a header (ends with colon)
            if (preg_match('/^(.+):(?:\s*$|\s+(.+))/', $line, $matches)) {
                $header = trim($matches[1]);
                $content = isset($matches[2]) ? trim($matches[2]) : '';
                
                $formatted[] = '<h3 class="text-lg font-semibold text-gray-900 mt-4 mb-2">' . $this->formatInlineElements($header) . '</h3>';
                if (!empty($content)) {
                    $formatted[] = '<p class="mb-3 leading-relaxed text-gray-700">' . $this->formatInlineElements($content) . '</p>';
                }
            } else {
                $formatted[] = '<p class="mb-3 leading-relaxed text-gray-700">' . $this->formatInlineElements($line) . '</p>';
            }
        }
        
        return '<div class="mb-4">' . implode("\n", $formatted) . '</div>';
    }

    /**
     * Format numbered lists
     */
    private function formatNumberedList(string $section): string
    {
        $items = preg_split('/(?=\d+\.\s+)/', $section);
        $listItems = [];
        
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            
            $item = preg_replace('/^\d+\.\s+/', '', $item);
            if (!empty($item)) {
                $listItems[] = '<li class="mb-2 leading-relaxed">' . $this->formatInlineElements($item) . '</li>';
            }
        }
        
        if (!empty($listItems)) {
            return '<ol class="list-decimal list-inside mb-4 space-y-1 ml-4">' . implode("\n", $listItems) . '</ol>';
        }
        
        return '';
    }

    /**
     * Format bullet lists
     */
    private function formatBulletList(string $section): string
    {
        $items = preg_split('/(?=[-*•]\s+)/', $section);
        $listItems = [];
        
        foreach ($items as $item) {
            $item = trim($item);
            if (empty($item)) continue;
            
            $item = preg_replace('/^[-*•]\s+/', '', $item);
            if (!empty($item)) {
                $listItems[] = '<li class="mb-2 leading-relaxed">' . $this->formatInlineElements($item) . '</li>';
            }
        }
        
        if (!empty($listItems)) {
            return '<ul class="list-disc list-inside mb-4 space-y-1 ml-4">' . implode("\n", $listItems) . '</ul>';
        }
        
        return '';
    }

    /**
     * Format key-value sections (statistics, data points)
     */
    private function formatKeyValueSection(string $section): string
    {
        $lines = explode("\n", $section);
        $formatted = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                $formatted[] = '<div class="flex justify-between items-start py-1 border-b border-gray-100">';
                $formatted[] = '<span class="font-medium text-gray-900">' . $this->formatInlineElements($key) . ':</span>';
                $formatted[] = '<span class="text-gray-700 ml-4">' . $this->formatInlineElements($value) . '</span>';
                $formatted[] = '</div>';
            } else {
                $formatted[] = '<p class="mb-2 leading-relaxed text-gray-700">' . $this->formatInlineElements($line) . '</p>';
            }
        }
        
        return '<div class="mb-4 bg-gray-50 p-3 rounded-lg">' . implode("\n", $formatted) . '</div>';
    }

    /**
     * Format regular paragraphs
     */
    private function formatParagraph(string $section): string
    {
        $lines = explode("\n", $section);
        $formattedLines = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $formattedLines[] = $this->formatInlineElements($line);
            }
        }
        
        if (!empty($formattedLines)) {
            // If it's a single line, make it a paragraph. If multiple lines, join with breaks
            if (count($formattedLines) === 1) {
                return '<p class="mb-4 leading-relaxed text-gray-700">' . $formattedLines[0] . '</p>';
            } else {
                return '<div class="mb-4 leading-relaxed text-gray-700">' . implode('<br>', $formattedLines) . '</div>';
            }
        }
        
        return '';
    }

    /**
     * Format inline elements like bold, italic, etc.
     */
    private function formatInlineElements(string $text): string
    {
        // Convert bold text (both ** and __ formats)
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong class="font-semibold text-gray-900">$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/', '<strong class="font-semibold text-gray-900">$1</strong>', $text);
        
        // Convert italic text
        $text = preg_replace('/\*(.*?)\*/', '<em class="italic text-gray-800">$1</em>', $text);
        $text = preg_replace('/_(.*?)_/', '<em class="italic text-gray-800">$1</em>', $text);
        
        // Convert inline code
        $text = preg_replace('/`([^`]+)`/', '<code class="bg-gray-100 px-2 py-1 rounded text-sm font-mono">$1</code>', $text);
        
        // Highlight numbers and percentages
        $text = preg_replace('/\b(\d+(?:,\d{3})*(?:\.\d+)?%?)\b/', '<span class="font-medium text-blue-700">$1</span>', $text);
        
        // Highlight bill references that aren't already linked
        $text = preg_replace('/\b([HS]\.?R?\.?\s*\d+)\b(?![^<]*<\/a>)/', '<span class="font-medium text-indigo-700">$1</span>', $text);
        
        return $text;
    }
}