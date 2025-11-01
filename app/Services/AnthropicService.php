<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use League\CommonMark\CommonMarkConverter;

class AnthropicService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.anthropic.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
        
        // Log API key status for debugging
        Log::info('AnthropicService initialized', [
            'api_key_configured' => !empty($this->apiKey),
            'api_key_length' => strlen($this->apiKey ?? ''),
            'api_key_prefix' => substr($this->apiKey ?? '', 0, 10) . '...'
        ]);
    }

    /**
     * Generate a summary of bill text using Claude
     */
    public function generateBillSummary(string $billText, string $billTitle, string $congressId): array
    {
        try {
            $prompt = $this->buildBillSummaryPrompt($billText, $billTitle, $congressId);
            
            // Log the request for debugging
            Log::info('Making Anthropic API request', [
                'url' => $this->baseUrl . '/messages',
                'model' => 'claude-3-5-sonnet',
                'api_key_length' => strlen($this->apiKey),
                'prompt_length' => strlen($prompt)
            ]);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01'
            ])->timeout(120)->post($this->baseUrl . '/messages', [
                'model' => 'claude-3-5-sonnet',
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            Log::info('Anthropic API response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body_preview' => substr($response->body(), 0, 500)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $summary = $data['content'][0]['text'] ?? '';
                
                return [
                    'success' => true,
                    'summary' => $summary,
                    'summary_html' => $this->convertMarkdownToHtml($summary),
                    'usage' => [
                        'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                        'output_tokens' => $data['usage']['output_tokens'] ?? 0
                    ]
                ];
            } else {
                Log::error('Anthropic API error', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'API request failed: ' . $response->status() . ' - ' . $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Anthropic service error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build the prompt for bill summary generation
     */
    private function buildBillSummaryPrompt(string $billText, string $billTitle, string $congressId): string
    {
        // Limit bill text to prevent token limit issues (roughly 50,000 characters = ~12,500 tokens)
        $maxTextLength = 50000;
        if (strlen($billText) > $maxTextLength) {
            $billText = substr($billText, 0, $maxTextLength) . "\n\n[Note: Bill text truncated due to length. This summary is based on the first portion of the bill.]";
        }
        
        return "Please analyze this congressional bill and provide a comprehensive summary. The bill is {$congressId}: {$billTitle}

BILL TEXT:
{$billText}

Please provide a structured summary with the following sections:

## Executive Summary
A brief 2-3 sentence overview of what this bill does.

## Key Provisions
- List the main provisions and changes this bill would make
- Include specific details about new programs, funding, or regulatory changes
- Highlight any significant policy shifts

## Impact Analysis
- Who would be affected by this legislation?
- What are the potential benefits and concerns?
- Any notable implementation timelines or requirements

## Funding & Implementation
- Any funding amounts or budget implications mentioned
- Implementation timeline and responsible agencies
- New programs or offices that would be created

## Political Context
- Brief note on the type of legislation (appropriations, authorization, etc.)
- Any bipartisan elements or potential areas of debate

Please keep the summary informative but accessible to the general public, avoiding excessive legal jargon while maintaining accuracy.";
    }

    /**
     * Generate a summary of executive order content using Claude
     */
    public function generateExecutiveOrderSummary(string $content, string $title, ?string $orderNumber = null): array
    {
        try {
            $prompt = $this->buildExecutiveOrderSummaryPrompt($content, $title, $orderNumber);
            
            // Log the request for debugging
            Log::info('Making Anthropic API request for executive order', [
                'url' => $this->baseUrl . '/messages',
                'model' => 'claude-3-5-sonnet',
                'api_key_length' => strlen($this->apiKey),
                'prompt_length' => strlen($prompt)
            ]);
            
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01'
            ])->timeout(120)->post($this->baseUrl . '/messages', [
                'model' => 'claude-3-5-sonnet',
                'max_tokens' => 4000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            Log::info('Anthropic API response for executive order', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body_preview' => substr($response->body(), 0, 500)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $summary = $data['content'][0]['text'] ?? '';
                
                return [
                    'success' => true,
                    'summary' => $summary,
                    'summary_html' => $this->convertMarkdownToHtml($summary),
                    'usage' => [
                        'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                        'output_tokens' => $data['usage']['output_tokens'] ?? 0
                    ]
                ];
            } else {
                Log::error('Anthropic API error for executive order', [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'API request failed: ' . $response->status() . ' - ' . $response->body()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Anthropic service error for executive order', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Build the prompt for executive order summary generation
     */
    private function buildExecutiveOrderSummaryPrompt(string $content, string $title, ?string $orderNumber = null): string
    {
        // Limit content to prevent token limit issues
        $maxTextLength = 50000;
        if (strlen($content) > $maxTextLength) {
            $content = substr($content, 0, $maxTextLength) . "\n\n[Note: Executive order text truncated due to length. This summary is based on the first portion of the order.]";
        }
        
        $orderInfo = $orderNumber ? "Executive Order {$orderNumber}: {$title}" : $title;
        
        return "Please analyze this presidential executive order and provide a comprehensive summary. The executive order is: {$orderInfo}

EXECUTIVE ORDER TEXT:
{$content}

Please provide a structured summary with the following sections:

## Executive Summary
A brief 2-3 sentence overview of what this executive order does and its main purpose.

## Key Directives
- List the main directives and actions this executive order mandates
- Include specific requirements for federal agencies
- Highlight any new policies, programs, or regulatory changes

## Affected Agencies & Stakeholders
- Which federal agencies are tasked with implementation?
- Who will be impacted by these changes (businesses, individuals, other entities)?
- Any coordination requirements between agencies

## Implementation & Timeline
- Any specific deadlines or timelines mentioned
- Reporting requirements or review processes
- New offices, committees, or working groups established

## Policy Impact
- What policy areas does this address (healthcare, environment, economy, etc.)?
- How does this relate to or change existing policies?
- Any potential benefits or concerns for different groups

## Background & Context
- Brief context on why this executive order was issued
- Any references to previous orders it modifies or revokes

Please keep the summary informative but accessible to the general public, avoiding excessive legal jargon while maintaining accuracy.";
    }

    /**
     * Generate a quick summary for display in lists
     */
    public function generateQuickSummary(string $billText, string $billTitle): array
    {
        try {
            $prompt = "Provide a concise 2-3 sentence summary of this congressional bill: {$billTitle}

BILL TEXT (excerpt):
" . substr($billText, 0, 3000) . "

Focus on the main purpose and key changes this bill would make. Keep it under 100 words and accessible to the general public.";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01'
            ])->timeout(30)->post($this->baseUrl . '/messages', [
                'model' => 'claude-3-haiku-20240307', // Using faster model for quick summaries
                'max_tokens' => 200,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $summary = $data['content'][0]['text'] ?? '';
                return [
                    'success' => true,
                    'summary' => $summary,
                    'summary_html' => $this->convertMarkdownToHtml($summary)
                ];
            }

            return ['success' => false, 'error' => 'API request failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a chat response for the congressional chatbot
     */
    public function generateChatResponse(string $prompt): array
    {
        try {
            Log::info('Making Anthropic chat API request', [
                'prompt_length' => strlen($prompt)
            ]);
            
            // Use shorter timeout and fewer tokens for faster responses
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01'
            ])->timeout(45)->post($this->baseUrl . '/messages', [
                'model' => 'claude-3-5-sonnet',
                'max_tokens' => 2000, // Reduced for faster responses
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $responseText = $data['content'][0]['text'] ?? '';
                
                return [
                    'success' => true,
                    'response' => $responseText,
                    'response_html' => $this->convertMarkdownToHtml($responseText),
                    'usage' => [
                        'input_tokens' => $data['usage']['input_tokens'] ?? 0,
                        'output_tokens' => $data['usage']['output_tokens'] ?? 0
                    ]
                ];
            } else {
                Log::error('Anthropic chat API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'error' => 'API request failed: ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error('Anthropic chat service error', [
                'message' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Service error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert markdown text to HTML
     */
    public function convertMarkdownToHtml(string $markdown): string
    {
        try {
            $converter = new CommonMarkConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);
            
            return $converter->convert($markdown)->getContent();
        } catch (\Exception $e) {
            Log::warning('Failed to convert markdown to HTML', [
                'error' => $e->getMessage(),
                'markdown_preview' => substr($markdown, 0, 100)
            ]);
            
            // Fallback: return original markdown with basic HTML formatting
            return '<div class="markdown-fallback">' . nl2br(htmlspecialchars($markdown)) . '</div>';
        }
    }
}
