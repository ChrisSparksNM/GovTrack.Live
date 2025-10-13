<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    // Middleware is applied via routes, no need for constructor

    /**
     * Display the user dashboard with tracked bills
     */
    public function index(): View
    {
        $user = auth()->user();
        $trackedBills = $user->trackedBills()
            ->orderBy('tracked_bills.created_at', 'desc')
            ->paginate(20);

        return view('dashboard', compact('trackedBills'));
    }
}
