<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Services\CongressApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserBillController extends Controller
{
    public function __construct(
        private CongressApiService $congressApiService
    ) {}

    /**
     * Track a bill for the authenticated user
     */
    public function track(string $congressId): JsonResponse
    {
        $user = auth()->user();
        
        // Check if bill is already being tracked
        if ($user->trackedBills()->where('congress_id', $congressId)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bill is already being tracked.'
            ]);
        }

        // Fetch bill details from API to store basic info
        $billData = $this->congressApiService->fetchBillDetails($congressId);
        
        if (!$billData) {
            return response()->json([
                'success' => false,
                'message' => 'Bill not found.'
            ], 404);
        }

        // Create or find the bill record
        $bill = Bill::updateOrCreate(
            ['congress_id' => $congressId],
            [
                'title' => $billData['title'],
                'number' => $billData['number'],
                'chamber' => $billData['chamber'],
                'introduced_date' => $billData['introduced_date'],
                'status' => $billData['status'],
                'sponsor_name' => $billData['sponsor_name'],
                'sponsor_party' => $billData['sponsor_party'],
                'sponsor_state' => $billData['sponsor_state'],
                'cosponsors' => $billData['cosponsors'],
            ]
        );

        $user->trackedBills()->attach($bill->id);

        return response()->json([
            'success' => true,
            'message' => 'Bill added to your tracking list.',
            'is_tracked' => true
        ]);
    }

    /**
     * Untrack a bill for the authenticated user
     */
    public function untrack(string $congressId): JsonResponse
    {
        $user = auth()->user();
        
        $bill = Bill::where('congress_id', $congressId)->first();
        
        if (!$bill || !$user->trackedBills()->where('bill_id', $bill->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Bill is not being tracked.'
            ]);
        }

        $user->trackedBills()->detach($bill->id);

        return response()->json([
            'success' => true,
            'message' => 'Bill removed from your tracking list.',
            'is_tracked' => false
        ]);
    }
}
