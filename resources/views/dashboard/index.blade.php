@extends('layouts.app')

@section('title', 'My Dashboard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-2">My Dashboard</h1>
        <p class="text-gray-600">Track and manage your followed bills</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $stats['total_tracked'] }}</h3>
                    <p class="text-gray-600 text-sm">Bills Tracked</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ $stats['recent_updates'] }}</h3>
                    <p class="text-gray-600 text-sm">Recent Updates</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold text-gray-800">{{ count($stats['by_chamber']) }}</h3>
                    <p class="text-gray-600 text-sm">Bill Types</p>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Assistant Widget -->
    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg shadow-lg p-6 mb-8 text-white">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <div class="flex items-center mb-3">
                    <div class="p-2 bg-white/20 rounded-lg mr-3">
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold">Congressional AI Assistant</h3>
                </div>
                <p class="text-white/90 mb-4">Get instant insights about Congress, bills, members, and legislative trends using AI-powered analysis of your comprehensive congressional database.</p>
                <div class="flex flex-wrap gap-2 mb-4">
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">Bill Analysis</span>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">Member Insights</span>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">Trend Analysis</span>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-sm">Statistics</span>
                </div>
            </div>
            <div class="ml-6">
                <a href="{{ route('chatbot.index') }}" class="inline-flex items-center px-6 py-3 bg-white text-blue-600 font-semibold rounded-lg hover:bg-gray-100 transition-colors duration-200 shadow-lg">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    Ask AI Assistant
                </a>
            </div>
        </div>
    </div>

    <!-- Bill Type Breakdown -->
    @if(!empty($stats['by_chamber']))
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">Bills by Type</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($stats['by_chamber'] as $type => $count)
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $count }}</div>
                        <div class="text-sm text-gray-600">{{ strtoupper($type) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Tracked Bills -->
    <div class="bg-white rounded-lg shadow-md">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">Your Tracked Bills</h3>
                <a href="{{ route('bills.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    Browse Bills →
                </a>
            </div>
        </div>

        @if($trackedBills->count() > 0)
            <div class="divide-y divide-gray-200">
                @foreach($trackedBills as $trackedBill)
                    @php $bill = $trackedBill->bill; @endphp
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center mb-2">
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-3">
                                        {{ $bill->congress_id }}
                                    </span>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ strtoupper($bill->type) }}
                                    </span>
                                </div>
                                
                                <h4 class="text-lg font-medium text-gray-900 mb-2">
                                    <a href="{{ route('bills.show', $bill->congress_id) }}" class="hover:text-blue-600">
                                        {{ $bill->title }}
                                    </a>
                                </h4>

                                @if($bill->sponsors->isNotEmpty())
                                    <p class="text-sm text-gray-600 mb-2">
                                        Sponsor: {{ $bill->sponsors->first()->full_name }} 
                                        ({{ $bill->sponsors->first()->party }}-{{ $bill->sponsors->first()->state }})
                                    </p>
                                @endif

                                @if($bill->latest_action_text)
                                    <div class="text-sm text-gray-600 mb-2">
                                        <strong>Latest Action:</strong> {{ Str::limit($bill->latest_action_text, 100) }}
                                        @if($bill->latest_action_date)
                                            <span class="text-gray-500">
                                                ({{ $bill->latest_action_date->format('M j, Y') }})
                                            </span>
                                        @endif
                                    </div>
                                @endif

                                @if($trackedBill->notes)
                                    <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded">
                                        <div class="text-sm text-yellow-800">
                                            <strong>Your Notes:</strong> {{ $trackedBill->notes }}
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="ml-4 flex flex-col items-end">
                                <div class="text-xs text-gray-500 mb-2">
                                    Tracked {{ $trackedBill->tracked_at->diffForHumans() }}
                                </div>
                                
                                <div class="flex space-x-2">
                                    <button onclick="editTracking('{{ $bill->congress_id }}', '{{ $trackedBill->notes }}')"
                                            class="text-blue-600 hover:text-blue-800 text-sm">
                                        Edit
                                    </button>
                                    <button onclick="untrackBill('{{ $bill->congress_id }}')"
                                            class="text-red-600 hover:text-red-800 text-sm">
                                        Untrack
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $trackedBills->links() }}
            </div>
        @else
            <div class="p-12 text-center">
                <div class="text-gray-400 mb-4">
                    <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No Bills Tracked Yet</h3>
                <p class="text-gray-600 mb-4">Start tracking bills to see them here in your dashboard.</p>
                <a href="{{ route('bills.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Browse Bills
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Edit Tracking Modal -->
<div id="edit-tracking-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit Tracking Notes</h3>
            </div>
            <form id="edit-tracking-form">
                <div class="px-6 py-4">
                    <label for="tracking-notes" class="block text-sm font-medium text-gray-700 mb-2">
                        Notes (optional)
                    </label>
                    <textarea id="tracking-notes" 
                              name="notes" 
                              rows="3" 
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Add your notes about this bill..."></textarea>
                </div>
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" 
                            onclick="closeEditModal()"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition-colors">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentBillId = null;

function editTracking(billId, currentNotes) {
    currentBillId = billId;
    document.getElementById('tracking-notes').value = currentNotes || '';
    document.getElementById('edit-tracking-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('edit-tracking-modal').classList.add('hidden');
    currentBillId = null;
}

function untrackBill(billId) {
    if (!confirm('Are you sure you want to stop tracking this bill?')) {
        return;
    }

    fetch(`/bills/${billId}/untrack`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to untrack bill');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while untracking the bill');
    });
}

document.getElementById('edit-tracking-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (!currentBillId) return;
    
    const formData = new FormData(this);
    
    fetch(`/bills/${currentBillId}/track`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            notes: formData.get('notes')
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeEditModal();
            location.reload();
        } else {
            alert(data.message || 'Failed to update tracking notes');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating tracking notes');
    });
});

// Close modal when clicking outside
document.getElementById('edit-tracking-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>
@endsection