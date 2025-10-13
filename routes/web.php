<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\BillVoteController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TrackedBillController;
use Illuminate\Support\Facades\Route;

// Redirect root to bills index
Route::get('/', function () {
    return redirect()->route('bills.index');
});

// Bill routes
Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
Route::get('/bill-text-versions/{congressId}', [BillController::class, 'fetchTextVersions'])->name('bills.text-versions');
Route::get('/bill-text-proxy/{congressId}', [BillController::class, 'fetchTextProxy'])->name('bills.text-proxy');
Route::get('/test-text-versions', function() { 
    return view('test-text-versions'); 
})->name('test.text-versions');
Route::post('/bills/{congressId}/summary', [BillController::class, 'generateSummary'])->name('bills.summary');
Route::get('/bills/{congressId}', [BillController::class, 'show'])->name('bills.show');

// Member routes
Route::get('/members', [MemberController::class, 'index'])->name('members.index');
Route::get('/members/{bioguideId}', [MemberController::class, 'show'])->name('members.show');

// Chatbot routes
Route::get('/chatbot', [ChatbotController::class, 'index'])->name('chatbot.index');
Route::get('/chatbot/suggestions', [ChatbotController::class, 'suggestions'])->name('chatbot.suggestions');

// Chatbot interaction routes (requires authentication)
Route::middleware('auth')->group(function () {
    Route::post('/chatbot/chat', [ChatbotController::class, 'chat'])->name('chatbot.chat');
    Route::post('/chatbot/clear', [ChatbotController::class, 'clearConversation'])->name('chatbot.clear');
});

// API routes for statistics
Route::get('/api/congress/stats', [App\Http\Controllers\Api\CongressStatsController::class, 'index'])->name('api.congress.stats');


// User bill tracking routes (requires authentication)
Route::middleware('auth')->group(function () {
    Route::post('/bills/{bill}/track', [TrackedBillController::class, 'track'])->name('bills.track');
    Route::delete('/bills/{bill}/untrack', [TrackedBillController::class, 'untrack'])->name('bills.untrack');
    Route::put('/bills/{bill}/track', [TrackedBillController::class, 'update'])->name('bills.track.update');
    Route::get('/dashboard', [TrackedBillController::class, 'dashboard'])->name('dashboard');
    
    // Bill voting routes
    Route::post('/bills/{bill}/vote', [BillVoteController::class, 'vote'])->name('bills.vote');
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Google OAuth routes
Route::get('/auth/google', [App\Http\Controllers\Auth\GoogleController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [App\Http\Controllers\Auth\GoogleController::class, 'callback'])->name('auth.google.callback');

require __DIR__.'/auth.php';


// Temporary debug route - remove after testing
Route::get('/debug-chatbot', function() {
    try {
        $chatbotService = app('App\Services\CongressChatbotService');
        $result = $chatbotService->askQuestion('What are the most popular policy areas this year?');
        
        return response()->json([
            'debug' => true,
            'success' => $result['success'],
            'method' => $result['method'] ?? 'unknown',
            'response_length' => strlen($result['response']),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'error' => $result['error'] ?? null,
            'first_200_chars' => substr($result['response'], 0, 200)
        ]);
    } catch (Exception $e) {
        return response()->json([
            'debug' => true,
            'success' => false,
            'exception' => $e->getMessage(),
            'file' => $e->getFile() . ':' . $e->getLine(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
        ], 500);
    }
});
