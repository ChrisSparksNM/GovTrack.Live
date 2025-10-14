<?php

namespace App\Http\Controllers;

use App\Models\ExecutiveOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExecutiveOrderController extends Controller
{
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