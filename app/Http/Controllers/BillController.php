<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Services\CongressApiService;
use App\Services\AnthropicService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Str;

class BillController extends Controller
{
    public function __construct(
        private CongressApiService $congressApiService,
        private AnthropicService $anthropicService
    ) {}

    /**
     * Display a listing of bills from database
     */
    public function index(Request $request): View
    {
        $search = $request->get('search', '');
        $hasTextFilter = $request->get('has_text') === '1';
        $congress = $request->get('congress', 119);
        $billType = $request->get('type', '');
        $sortBy = $request->get('sort', 'recent_activity');
        $sortOrder = $request->get('order', 'desc');
        $perPage = min(100, max(10, (int) $request->get('per_page', 25)));
        
        try {
            // Build query
            $query = Bill::where('congress', $congress)
                        ->with(['sponsors', 'textVersions' => function($q) {
                            $q->latest('date')->limit(1);
                        }, 'votes']);
            
            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('congress_id', 'LIKE', "%{$search}%")
                      ->orWhere('policy_area', 'LIKE', "%{$search}%")
                      ->orWhereHas('sponsors', function($sq) use ($search) {
                          $sq->where('full_name', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('subjects', function($sq) use ($search) {
                          $sq->where('name', 'LIKE', "%{$search}%");
                      });
                });
            }
            
            // Apply text filter
            if ($hasTextFilter) {
                $query->whereNotNull('bill_text');
            }
            
            // Apply bill type filter
            if (!empty($billType)) {
                $query->where('type', strtoupper($billType));
            }
            
            // Apply sorting
            $validSorts = ['introduced_date', 'most_voted', 'recent_activity'];
            if (in_array($sortBy, $validSorts)) {
                if ($sortBy === 'most_voted') {
                    $query->orderByMostVoted($sortOrder === 'asc' ? 'asc' : 'desc');
                } elseif ($sortBy === 'recent_activity') {
                    $query->orderByRecentActivity($sortOrder === 'asc' ? 'asc' : 'desc');
                } else {
                    $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
                }
            } else {
                $query->orderByRecentActivity('desc');
            }
            
            // Get paginated results
            $bills = $query->paginate($perPage);
            
            // Append query parameters to pagination links
            $bills->appends($request->query());
            
            // Get summary statistics
            $stats = $this->getBillsStatistics(
                $congress, 
                $search ?: null, 
                $hasTextFilter, 
                $billType ?: null
            );
            
            return view('bills.index', compact(
                'bills',
                'search',
                'hasTextFilter',
                'congress',
                'billType',
                'sortBy',
                'sortOrder',
                'perPage',
                'stats'
            ));
            
        } catch (\Exception $e) {
            Log::error('Error fetching bills from database: ' . $e->getMessage());
            
            // Return empty paginator on error
            $bills = new LengthAwarePaginator([], 0, $perPage, 1);
            $stats = ['total' => 0, 'with_text' => 0, 'recent_actions' => 0];
            
            return view('bills.index', compact(
                'bills',
                'search',
                'hasTextFilter', 
                'congress',
                'billType',
                'sortBy',
                'sortOrder',
                'perPage',
                'stats'
            ))->with('error', 'Unable to fetch bills from database. Please try again later.');
        }
    }

    /**
     * Get bills statistics for the current filters
     */
    private function getBillsStatistics(int $congress, ?string $search, bool $hasTextFilter, ?string $billType): array
    {
        $baseQuery = Bill::where('congress', $congress);
        
        // Apply same filters as main query
        if (!empty($search)) {
            $baseQuery->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('congress_id', 'LIKE', "%{$search}%")
                  ->orWhere('policy_area', 'LIKE', "%{$search}%");
            });
        }
        
        if (!empty($billType)) {
            $baseQuery->where('type', strtoupper($billType));
        }
        
        $total = $baseQuery->count();
        $withText = (clone $baseQuery)->whereNotNull('bill_text')->count();
        $recentActions = (clone $baseQuery)->where('latest_action_date', '>=', now()->subDays(7))->count();
        
        return [
            'total' => $total,
            'with_text' => $withText,
            'recent_actions' => $recentActions
        ];
    }

    /**
     * Show a specific bill from database
     */
    public function show(string $congressId): View
    {
        try {
            // Find bill in database with all related data
            $bill = Bill::where('congress_id', $congressId)
                       ->with([
                           'sponsors',
                           'actions' => function($q) {
                               $q->orderBy('action_date', 'desc')
                                 ->orderBy('action_time', 'desc')
                                 ->limit(50); // Show last 50 actions
                           },
                           'summaries' => function($q) {
                               $q->orderBy('action_date', 'desc');
                           },
                           'subjects',
                           'cosponsors' => function($q) {
                               $q->orderBy('sponsorship_date', 'desc')
                                 ->limit(100); // Show first 100 cosponsors
                           },
                           'textVersions' => function($q) {
                               $q->orderBy('date', 'desc');
                           },
                           'votes'
                       ])
                       ->first();
            
            if (!$bill) {
                abort(404, 'Bill not found in database');
            }

            // Get additional statistics
            $stats = [
                'total_actions' => $bill->actions_count,
                'total_cosponsors' => $bill->cosponsors_count,
                'total_summaries' => $bill->summaries_count,
                'total_subjects' => $bill->subjects_count,
                'total_text_versions' => $bill->text_versions_count,
                'last_updated' => $bill->last_scraped_at,
                'has_full_text' => !empty($bill->bill_text),
                'text_length' => $bill->bill_text ? strlen($bill->bill_text) : 0
            ];

            return view('bills.show', compact('bill', 'congressId', 'stats'));
            
        } catch (\Exception $e) {
            Log::error('Error fetching bill from database: ' . $e->getMessage(), [
                'congress_id' => $congressId
            ]);
            
            abort(500, 'Unable to fetch bill details from database');
        }
    }

    /**
     * Fetch text versions for a bill from database
     */
    public function fetchTextVersions(string $congressId): JsonResponse
    {
        try {
            $bill = Bill::where('congress_id', $congressId)
                       ->with('textVersions')
                       ->first();

            if (!$bill) {
                return response()->json([
                    'success' => false,
                    'error' => 'Bill not found in database'
                ], 404);
            }

            $textVersions = $bill->textVersions->map(function($version) {
                return [
                    'type' => $version->type,
                    'date' => $version->date ? $version->date->format('Y-m-d') : null,
                    'is_current' => $version->is_current,
                    'formats' => array_filter([
                        $version->pdf_url ? ['type' => 'PDF', 'url' => $version->pdf_url] : null,
                        $version->xml_url ? ['type' => 'XML', 'url' => $version->xml_url] : null,
                        $version->html_url ? ['type' => 'HTML', 'url' => $version->html_url] : null,
                    ])
                ];
            });

            return response()->json([
                'success' => true,
                'textVersions' => $textVersions,
                'count' => $textVersions->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching text versions from database: ' . $e->getMessage(), [
                'congress_id' => $congressId
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch text versions'
            ], 500);
        }
    }

    /**
     * Fetch bill text from database
     */
    public function fetchTextProxy(string $congressId): JsonResponse
    {
        try {
            $bill = Bill::where('congress_id', $congressId)->first();
            
            if (!$bill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill not found in database.'
                ], 404);
            }

            if (empty($bill->bill_text)) {
                return response()->json([
                    'success' => false,
                    'message' => "Bill text not available for {$congressId}. The text may not have been scraped yet.",
                    'congress_url' => $bill->legislation_url
                ], 404);
            }

            return response()->json([
                'success' => true,
                'text' => $bill->bill_text,
                'formatted_url' => $bill->legislation_url,
                'source' => 'Database',
                'last_updated' => $bill->last_scraped_at ? $bill->last_scraped_at->format('Y-m-d H:i:s') : null
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching bill text from database: ' . $e->getMessage(), [
                'congress_id' => $congressId
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching bill text.'
            ], 500);
        }
    }

    /**
     * Generate AI summary for a bill using Anthropic Claude
     */
    public function generateSummary(string $congressId): JsonResponse
    {
        try {
            $bill = Bill::where('congress_id', $congressId)->first();
            
            if (!$bill) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill not found in database.'
                ], 404);
            }

            if (empty($bill->bill_text)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bill text not available for summary generation. Please ensure the bill text has been scraped.'
                ], 400);
            }

            // Check if we already have a recent AI summary (within 7 days)
            if ($bill->ai_summary && 
                $bill->ai_summary_generated_at && 
                $bill->ai_summary_generated_at->gt(now()->subDays(7))) {
                
                // Convert cached markdown summary to HTML
                $summaryHtml = $this->anthropicService->convertMarkdownToHtml($bill->ai_summary);
                
                return response()->json([
                    'success' => true,
                    'summary' => $bill->ai_summary,
                    'summary_html' => $summaryHtml,
                    'generated_at' => $bill->ai_summary_generated_at->format('M j, Y g:i A'),
                    'cached' => true
                ]);
            }

            // Generate new AI summary using Anthropic
            $result = $this->anthropicService->generateBillSummary(
                $bill->bill_text,
                $bill->title,
                $bill->congress_id
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate AI summary: ' . ($result['error'] ?? 'Unknown error')
                ], 500);
            }

            // Store the AI summary in the database
            $bill->update([
                'ai_summary' => $result['summary'],
                'ai_summary_html' => $result['summary_html'] ?? null,
                'ai_summary_generated_at' => now(),
                'ai_summary_metadata' => [
                    'model' => 'claude-3-5-sonnet-20250106',
                    'input_tokens' => $result['usage']['input_tokens'] ?? 0,
                    'output_tokens' => $result['usage']['output_tokens'] ?? 0,
                    'generated_at' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'success' => true,
                'summary' => $result['summary'],
                'summary_html' => $result['summary_html'] ?? null,
                'generated_at' => now()->format('M j, Y g:i A'),
                'cached' => false,
                'usage' => $result['usage'] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating AI summary: ' . $e->getMessage(), [
                'congress_id' => $congressId,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the AI summary. Please try again later.'
            ], 500);
        }
    }


}