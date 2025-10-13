<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('My Tracked Bills') }}
            </h2>
            <a href="{{ route('bills.index') }}" 
               class="text-indigo-600 hover:text-indigo-500 text-sm font-medium">
                Browse Bills →
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($trackedBills->count() > 0)
                        <div class="mb-4">
                            <p class="text-gray-600">You are tracking {{ $trackedBills->total() }} {{ Str::plural('bill', $trackedBills->total()) }}.</p>
                        </div>
                        
                        <div class="space-y-6">
                            @foreach($trackedBills as $bill)
                                <div class="border-b border-gray-200 pb-6 last:border-b-0">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                                <a href="{{ route('bills.show', $bill->congress_id) }}" 
                                                   class="hover:text-indigo-600 transition-colors">
                                                    {{ $bill->title }}
                                                </a>
                                            </h3>
                                            
                                            <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-2">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $bill->chamber === 'house' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ ucfirst($bill->chamber) }}
                                                </span>
                                                <span>{{ $bill->number }}</span>
                                                <span>Introduced: {{ $bill->introduced_date->format('M j, Y') }}</span>
                                                <span class="text-green-600">Tracked: {{ $bill->pivot->created_at->format('M j, Y') }}</span>
                                            </div>
                                            
                                            <div class="text-sm text-gray-600 mb-2">
                                                <strong>Sponsor:</strong> {{ $bill->sponsor_name }}
                                                @if($bill->sponsor_party && $bill->sponsor_state)
                                                    ({{ $bill->sponsor_party }}-{{ $bill->sponsor_state }})
                                                @endif
                                            </div>
                                            
                                            <div class="text-sm text-gray-600">
                                                <strong>Status:</strong> {{ $bill->status }}
                                            </div>
                                        </div>
                                        
                                        <div class="ml-4 flex flex-col gap-2">
                                            <a href="{{ route('bills.show', $bill->congress_id) }}" 
                                               class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                View Details
                                            </a>
                                            
                                            <button onclick="untrackBill('{{ $bill->congress_id }}')" 
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                                Untrack
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-6">
                            {{ $trackedBills->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <div class="text-gray-500 text-lg mb-2">No tracked bills yet</div>
                            <p class="text-gray-400 mb-6">
                                Start tracking bills to monitor their progress and stay informed about legislation that matters to you.
                            </p>
                            <a href="{{ route('bills.index') }}" 
                               class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Browse Bills
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function untrackBill(congressId) {
            if (confirm('Are you sure you want to stop tracking this bill?')) {
                fetch(`/bills/${congressId}/track`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        }
    </script>
    @endpush
</x-app-layout>
