<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Bill;
use App\Models\BillSponsor;
use App\Models\BillCosponsor;
use App\Services\CongressApiService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

class MemberController extends Controller
{
    public function __construct(
        private CongressApiService $congressApiService
    ) {}
    /**
     * Display a listing of members
     */
    public function index(Request $request): View
    {
        $search = $request->get('search', '');
        $party = $request->get('party', '');
        $state = $request->get('state', '');
        $chamber = $request->get('chamber', '');
        $currentOnly = $request->get('current_only', '1') === '1';
        $sortBy = $request->get('sort', 'last_name');
        $sortOrder = $request->get('order', 'asc');
        $perPage = min(50, max(10, (int) $request->get('per_page', 25)));

        $query = Member::query();

        // Apply filters
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('full_name', 'LIKE', "%{$search}%")
                  ->orWhere('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('state', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($party)) {
            $query->where('party_abbreviation', $party);
        }

        if (!empty($state)) {
            $query->where('state', $state);
        }

        if (!empty($chamber)) {
            $query->where('chamber', $chamber);
        }

        if ($currentOnly) {
            $query->current();
        }

        // Apply sorting
        $validSorts = ['last_name', 'first_name', 'state', 'party_abbreviation', 'sponsored_legislation_count', 'cosponsored_legislation_count'];
        if (in_array($sortBy, $validSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('last_name', 'asc');
        }

        $members = $query->paginate($perPage);
        $members->appends($request->query());

        // Get filter options
        $parties = Member::distinct()->pluck('party_abbreviation')->filter()->sort();
        $states = Member::distinct()->pluck('state')->filter()->sort();
        $chambers = Member::distinct()->pluck('chamber')->filter()->sort();

        return view('members.index', compact(
            'members',
            'search',
            'party',
            'state',
            'chamber',
            'currentOnly',
            'sortBy',
            'sortOrder',
            'perPage',
            'parties',
            'states',
            'chambers'
        ));
    }

    /**
     * Display the specified member
     */
    public function show(string $bioguideId): View
    {
        $member = Member::where('bioguide_id', $bioguideId)->first();
        
        // If member doesn't exist, try to fetch from Congress API
        if (!$member) {
            try {
                $memberData = $this->congressApiService->getMemberDetails($bioguideId);
                
                if ($memberData) {
                    $member = Member::create([
                        'bioguide_id' => $bioguideId,
                        'first_name' => $memberData['first_name'] ?? '',
                        'last_name' => $memberData['last_name'] ?? '',
                        'full_name' => $memberData['full_name'] ?? '',
                        'direct_order_name' => $memberData['direct_order_name'] ?? '',
                        'inverted_order_name' => $memberData['inverted_order_name'] ?? '',
                        'honorific_name' => $memberData['honorific_name'] ?? null,
                        'party_abbreviation' => $memberData['party_abbreviation'] ?? '',
                        'party_name' => $memberData['party_name'] ?? '',
                        'state' => $memberData['state'] ?? null,
                        'district' => $memberData['district'] ?? null,
                        'chamber' => $memberData['chamber'] ?? null,
                        'birth_year' => $memberData['birth_year'] ?? null,
                        'current_member' => $memberData['current_member'] ?? true,
                        'image_url' => $memberData['image_url'] ?? null,
                        'image_attribution' => $memberData['image_attribution'] ?? null,
                        'official_website_url' => $memberData['official_website_url'] ?? null,
                        'office_address' => $memberData['office_address'] ?? null,
                        'office_city' => $memberData['office_city'] ?? null,
                        'office_phone' => $memberData['office_phone'] ?? null,
                        'office_zip_code' => $memberData['office_zip_code'] ?? null,
                        'sponsored_legislation_count' => $memberData['sponsored_legislation_count'] ?? 0,
                        'cosponsored_legislation_count' => $memberData['cosponsored_legislation_count'] ?? 0,
                        'party_history' => $memberData['party_history'] ?? null,
                        'previous_names' => $memberData['previous_names'] ?? null,
                        'last_updated_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Failed to fetch member profile for {$bioguideId}: " . $e->getMessage());
            }
        }
        
        if (!$member) {
            abort(404, 'Member not found');
        }

        // Get sponsored bills
        $sponsoredBills = Bill::whereHas('sponsors', function($query) use ($bioguideId) {
            $query->where('bioguide_id', $bioguideId);
        })
        ->with(['sponsors', 'votes'])
        ->orderBy('introduced_date', 'desc')
        ->limit(20)
        ->get();

        // Get cosponsored bills
        $cosponsoredBills = Bill::whereHas('cosponsors', function($query) use ($bioguideId) {
            $query->where('bioguide_id', $bioguideId);
        })
        ->with(['sponsors', 'votes'])
        ->orderBy('introduced_date', 'desc')
        ->limit(20)
        ->get();

        // Get statistics
        $stats = [
            'total_sponsored' => Bill::whereHas('sponsors', function($query) use ($bioguideId) {
                $query->where('bioguide_id', $bioguideId);
            })->count(),
            'total_cosponsored' => Bill::whereHas('cosponsors', function($query) use ($bioguideId) {
                $query->where('bioguide_id', $bioguideId);
            })->count(),
            'recent_sponsored' => Bill::whereHas('sponsors', function($query) use ($bioguideId) {
                $query->where('bioguide_id', $bioguideId);
            })->where('introduced_date', '>=', now()->subMonths(6))->count(),
            'recent_cosponsored' => Bill::whereHas('cosponsors', function($query) use ($bioguideId) {
                $query->where('bioguide_id', $bioguideId);
            })->where('introduced_date', '>=', now()->subMonths(6))->count(),
        ];

        return view('members.show', compact(
            'member',
            'sponsoredBills',
            'cosponsoredBills',
            'stats'
        ));
    }
}
