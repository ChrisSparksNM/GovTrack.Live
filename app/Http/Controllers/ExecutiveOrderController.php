<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use App\Services\AnthropicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutiveOrderController extends Controller
{
    protected AnthropicService $anthropicService;

    public function __construct(AnthropicService $anthropicService)
    {
        $this->anthropicService = $anthropicService;
    }
    /**
     * Display a listing of executive orders
     */
    public function index(Request $request): View
    {
        $query = ExecutiveOrder::query();
        
        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('order_number', 'LIKE', "%{$searchTerm}%");
            });
        }
        
        // Filter by year
        if ($request->filled('year')) {
            $query->whereYear('signed_date', $request->get('year'));
        }
        
        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        
        // Sort options
        $sortBy = $request->get('sort', 'signed_date');
        $sortDirection = $request->get('direction', 'desc');
        
        $allowedSorts = ['signed_date', 'title', 'order_number', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDirection);
        }
        
        $executiveOrders = $query->paginate(20)->withQueryString();
        
        // Get filter options
        $years = ExecutiveOrder::selectRaw('YEAR(signed_date) as year')
            ->groupBy('year')
            ->orderBy('year', 'desc')
            ->pluck('year');
            
        $statuses = ExecutiveOrder::select('status')
            ->groupBy('status')
            ->pluck('status');
        
        return view('executive-orders.index', compact(
            'executiveOrders', 
            'years', 
            'statuses'
        ));
    }    
 
   /**
     * Display the specified executive order
     */
    public function show(ExecutiveOrder $executiveOrder): View
    {
        return view('executive-orders.show', compact('executiveOrder'));
    }
    
    /**
     * Get executive orders data for API/AJAX requests
     */
    public function api(Request $request)
    {
        $query = ExecutiveOrder::query();
        
        if ($request->filled('search')) {
            $searchTerm = $request->get('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('content', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('order_number', 'LIKE', "%{$searchTerm}%");
            });
        }
        
        if ($request->filled('year')) {
            $query->whereYear('signed_date', $request->get('year'));
        }
        
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }
        
        $executiveOrders = $query->orderBy('signed_date', 'desc')
            ->paginate(20);
        
        return response()->json([
            'success' => true,
            'data' => $executiveOrders->items(),
            'pagination' => [
                'current_page' => $executiveOrders->currentPage(),
                'last_page' => $executiveOrders->lastPage(),
                'per_page' => $executiveOrders->perPage(),
                'total' => $executiveOrders->total(),
            ]
        ]);
    }
    
    /**
     * Get statistics for executive orders
     */
    public function stats()
    {
        $stats = [
            'total' => ExecutiveOrder::count(),
            'current_year' => ExecutiveOrder::whereYear('signed_date', now()->year)->count(),
            'last_30_days' => ExecutiveOrder::where('signed_date', '>=', now()->subDays(30))->count(),
            'fully_scraped' => ExecutiveOrder::where('is_fully_scraped', true)->count(),
            'by_year' => ExecutiveOrder::selectRaw('YEAR(signed_date) as year, COUNT(*) as count')
                ->groupBy('year')
                ->orderBy('year', 'desc')
                ->get(),
            'by_status' => ExecutiveOrder::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get(),
            'recent_orders' => ExecutiveOrder::orderBy('signed_date', 'desc')
                ->limit(5)
                ->get(['id', 'title', 'signed_date', 'order_number', 'slug'])
        ];
        
        return response()->json([
            'success' => true,
            'stats' => $stats
        ]);
    }
}    

    /**
     * Generate AI summary for an executive order using Anthropic Claude
     */
    public function generateSummary(ExecutiveOrder $executiveOrder): JsonResponse
    {
        try {
            if (empty($executiveOrder->content)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Executive order content not available for summary generation. Please ensure the content has been scraped.'
                ], 400);
            }

            // Check if we already have a recent AI summary (within 7 days)
            if ($executiveOrder->ai_summary && 
                $executiveOrder->ai_summary_generated_at && 
                $executiveOrder->ai_summary_generated_at->gt(now()->subDays(7))) {
                
                // Convert cached markdown summary to HTML
                $summaryHtml = $this->anthropicService->convertMarkdownToHtml($executiveOrder->ai_summary);
                
                return response()->json([
                    'success' => true,
                    'summary' => $executiveOrder->ai_summary,
                    'summary_html' => $summaryHtml,
                    'generated_at' => $executiveOrder->ai_summary_generated_at->format('M j, Y g:i A'),
                    'cached' => true
                ]);
            }

            // Generate new AI summary using Anthropic
            $result = $this->anthropicService->generateExecutiveOrderSummary(
                $executiveOrder->content,
                $executiveOrder->title,
                $executiveOrder->order_number
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate AI summary: ' . ($result['error'] ?? 'Unknown error')
                ], 500);
            }

            // Store the AI summary in the database
            $executiveOrder->update([
                'ai_summary' => $result['summary'],
                'ai_summary_html' => $result['summary_html'] ?? null,
                'ai_summary_generated_at' => now(),
                'ai_summary_metadata' => [
                    'model' => 'claude-3-5-sonnet-20241022',
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
            \Log::error('Error generating executive order AI summary', [
                'executive_order_id' => $executiveOrder->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating the summary. Please try again later.'
            ], 500);
        }
    }