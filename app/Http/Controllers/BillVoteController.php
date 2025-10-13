<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillVoteController extends Controller
{
    /**
     * Vote on a bill (thumbs up or down)
     */
    public function vote(Request $request, $congressId)
    {
        $request->validate([
            'vote_type' => 'required|in:up,down'
        ]);

        $user = Auth::user();
        $bill = Bill::where('congress_id', $congressId)->firstOrFail();
        
        // Check if user already voted
        $existingVote = BillVote::where('user_id', $user->id)
                               ->where('bill_id', $bill->id)
                               ->first();

        $voteType = $request->input('vote_type');

        if ($existingVote) {
            if ($existingVote->vote_type === $voteType) {
                // Same vote - remove it (toggle off)
                $existingVote->delete();
                $userVote = null;
            } else {
                // Different vote - update it
                $existingVote->update(['vote_type' => $voteType]);
                $userVote = $voteType;
            }
        } else {
            // New vote
            BillVote::create([
                'user_id' => $user->id,
                'bill_id' => $bill->id,
                'vote_type' => $voteType,
            ]);
            $userVote = $voteType;
        }

        // Get updated counts
        $upvotes = BillVote::where('bill_id', $bill->id)->where('vote_type', 'up')->count();
        $downvotes = BillVote::where('bill_id', $bill->id)->where('vote_type', 'down')->count();

        return response()->json([
            'success' => true,
            'user_vote' => $userVote,
            'upvotes' => $upvotes,
            'downvotes' => $downvotes,
            'message' => $userVote ? 'Vote recorded!' : 'Vote removed!'
        ]);
    }
}
