<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\TrackedBill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TrackedBillController extends Controller
{
    // Middleware is handled in routes/web.php

    /**
     * Display the user's dashboard with tracked bills
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        $trackedBills = TrackedBill::with(['bill.sponsors', 'bill.actions' => function($query) {
            $query->latest('action_date')->limit(1);
        }])
        ->where('user_id', $user->id)
        ->orderBy('tracked_at', 'desc')
        ->paginate(20);

        $stats = [
            'total_tracked' => $trackedBills->total(),
            'recent_updates' => $this->getRecentUpdates($user),
            'by_chamber' => $this->getTrackingStatsByType($user),
        ];

        return view('dashboard.index', compact('trackedBills', 'stats'));
    }

    /**
     * Track a bill for the authenticated user
     */
    public function track(Request $request, $congressId)
    {
        $user = Auth::user();
        $bill = Bill::where('congress_id', $congressId)->firstOrFail();
        
        // Check if already tracking
        $existingTrack = TrackedBill::where('user_id', $user->id)
                                   ->where('bill_id', $bill->id)
                                   ->first();
        
        if ($existingTrack) {
            return response()->json([
                'success' => false,
                'message' => 'You are already tracking this bill.',
                'is_tracked' => true
            ]);
        }

        // Create tracking record
        TrackedBill::create([
            'user_id' => $user->id,
            'bill_id' => $bill->id,
            'notes' => $request->input('notes'),
            'notification_preferences' => $request->input('notifications', []),
            'tracked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bill added to your tracking list!',
            'is_tracked' => true
        ]);
    }

    /**
     * Stop tracking a bill
     */
    public function untrack($congressId)
    {
        $user = Auth::user();
        $bill = Bill::where('congress_id', $congressId)->firstOrFail();
        
        $deleted = TrackedBill::where('user_id', $user->id)
                             ->where('bill_id', $bill->id)
                             ->delete();

        if ($deleted) {
            return response()->json([
                'success' => true,
                'message' => 'Bill removed from your tracking list.',
                'is_tracked' => false
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Bill was not being tracked.',
            'is_tracked' => false
        ]);
    }

    /**
     * Update tracking notes or preferences
     */
    public function update(Request $request, $congressId)
    {
        $user = Auth::user();
        $bill = Bill::where('congress_id', $congressId)->firstOrFail();
        
        $trackedBill = TrackedBill::where('user_id', $user->id)
                                 ->where('bill_id', $bill->id)
                                 ->first();

        if (!$trackedBill) {
            return response()->json([
                'success' => false,
                'message' => 'You are not tracking this bill.'
            ]);
        }

        $trackedBill->update([
            'notes' => $request->input('notes'),
            'notification_preferences' => $request->input('notifications', []),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tracking preferences updated!'
        ]);
    }

    /**
     * Get recent updates for tracked bills
     */
    private function getRecentUpdates($user)
    {
        return TrackedBill::with(['bill.actions' => function($query) {
            $query->where('action_date', '>=', now()->subDays(7))
                  ->orderBy('action_date', 'desc');
        }])
        ->where('user_id', $user->id)
        ->get()
        ->filter(function($trackedBill) {
            return $trackedBill->bill->actions->isNotEmpty();
        })
        ->count();
    }

    /**
     * Get tracking statistics by bill type
     */
    private function getTrackingStatsByType($user)
    {
        return TrackedBill::with('bill')
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('bill.type')
            ->map(function($group) {
                return $group->count();
            })
            ->toArray();
    }
}