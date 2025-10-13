<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class CongressStatsController extends Controller
{
    /**
     * Get comprehensive congressional statistics
     */
    public function index(): JsonResponse
    {
        $stats = Cache::remember('congress_stats', 300, function () {
            return [
                'overview' => $this->getOverviewStats(),
                'bills' => $this->getBillStats(),
                'members' => $this->getMemberStats(),
                'trends' => $this->getTrendStats(),
                'updated_at' => now()->toISOString()
            ];
        });

        return response()->json($stats);
    }

    /**
     * Get overview statistics
     */
    private function getOverviewStats(): array
    {
        return [
            'total_bills' => Bill::count(),
            'total_members' => Member::count(),
            'current_members' => Member::current()->count(),
            'bills_this_congress' => Bill::where('congress', 118)->count(),
        ];
    }

    /**
     * Get bill statistics
     */
    private function getBillStats(): array
    {
        return [
            'by_type' => Bill::select('type', DB::raw('count(*) as count'))
                ->groupBy('type')
                ->orderBy('count', 'desc')
                ->get()
                ->toArray(),
            'by_chamber' => Bill::select('origin_chamber', DB::raw('count(*) as count'))
                ->groupBy('origin_chamber')
                ->get()
                ->toArray(),
            'recent_count' => Bill::where('introduced_date', '>=', now()->subDays(30))->count(),
            'with_text' => Bill::whereNotNull('bill_text')->count(),
            'with_ai_summary' => Bill::whereNotNull('ai_summary')->count(),
        ];
    }

    /**
     * Get member statistics
     */
    private function getMemberStats(): array
    {
        return [
            'by_party' => Member::current()
                ->select('party_abbreviation', 'party_name', DB::raw('count(*) as count'))
                ->groupBy('party_abbreviation', 'party_name')
                ->orderBy('count', 'desc')
                ->get()
                ->toArray(),
            'by_chamber' => Member::current()
                ->select('chamber', DB::raw('count(*) as count'))
                ->groupBy('chamber')
                ->get()
                ->toArray(),
            'by_state' => Member::current()
                ->select('state', DB::raw('count(*) as count'))
                ->groupBy('state')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
                ->toArray(),
        ];
    }

    /**
     * Get trend statistics
     */
    private function getTrendStats(): array
    {
        $dateFormat = config('database.default') === 'mysql' 
            ? 'DATE_FORMAT(introduced_date, "%Y-%m")' 
            : 'strftime("%Y-%m", introduced_date)';

        return [
            'bills_by_month' => Bill::select(
                DB::raw($dateFormat . ' as month'),
                DB::raw('count(*) as count')
            )
            ->where('introduced_date', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12)
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
        ];
    }
}