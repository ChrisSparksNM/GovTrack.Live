@extends('layouts.app')

@section('title', 'Executive Orders')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Executive Orders</h1>
                    <p class="text-gray-600 mt-2">Presidential executive orders and actions</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-500">
                        Total: {{ number_format($executiveOrders->total()) }} orders
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <form method="GET" action="{{ route('executive-orders.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <input 
                            type="text" 
                            name="search" 
                            id="search"
                            value="{{ request('search') }}"
                            placeholder="Search by title, content, or order number..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                    </div>
                    
                    <!-- Year Filter -->
                    <div>
                        <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                        <select name="year" id="year" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Years</option>
                            @foreach($years as $year)
                                <option value="{{ $year }}" {{ request('year') == $year ? 'selected' : '' }}>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                    {{ ucfirst($status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center justify-between">
                    <div class="flex space-x-2">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                            Apply Filters
                        </button>
                        <a href="{{ route('executive-orders.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300 transition-colors">
                            Clear
                        </a>
                    </div>
                    
                    <!-- Sort Options -->
                    <div class="flex items-center space-x-2">
                        <label class="text-sm text-gray-600">Sort by:</label>
                        <select name="sort" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm">
                            <option value="signed_date" {{ request('sort') == 'signed_date' ? 'selected' : '' }}>Date Signed</option>
                            <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>Title</option>
                            <option value="order_number" {{ request('sort') == 'order_number' ? 'selected' : '' }}>Order Number</option>
                        </select>
                        <select name="direction" onchange="this.form.submit()" class="border border-gray-300 rounded px-2 py-1 text-sm">
                            <option value="desc" {{ request('direction') == 'desc' ? 'selected' : '' }}>Newest First</option>
                            <option value="asc" {{ request('direction') == 'asc' ? 'selected' : '' }}>Oldest First</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Executive Orders List -->
        <div class="space-y-4">
            @forelse($executiveOrders as $order)
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    @if($order->order_number)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            EO {{ $order->order_number }}
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        {{ $order->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        {{ $order->signed_date->format('M j, Y') }}
                                    </span>
                                </div>
                                
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                    <a href="{{ route('executive-orders.show', $order) }}" class="hover:text-blue-600 transition-colors">
                                        {{ $order->title }}
                                    </a>
                                </h3>
                                
                                @if($order->summary)
                                    <p class="text-gray-600 text-sm mb-3 line-clamp-2">
                                        {{ Str::limit($order->summary, 200) }}
                                    </p>
                                @endif
                                
                                <div class="flex items-center space-x-4 text-sm text-gray-500">
                                    @if($order->hasContent())
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            {{ number_format($order->word_count) }} words
                                        </span>
                                    @endif
                                    
                                    @if($order->reading_time)
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $order->reading_time }} min read
                                        </span>
                                    @endif
                                    
                                    @if($order->topics && count($order->topics) > 0)
                                        <span class="flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                            </svg>
                                            {{ count($order->topics) }} topics
                                        </span>
                                    @endif
                                </div>
                            </div>
                            
                            <div class="ml-4">
                                <a href="{{ route('executive-orders.show', $order) }}" 
                                   class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    View Details
                                    <svg class="ml-2 -mr-0.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No executive orders found</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if(request()->hasAny(['search', 'year', 'status']))
                            Try adjusting your search criteria or filters.
                        @else
                            Executive orders will appear here once they are scraped from the White House website.
                        @endif
                    </p>
                    @if(!request()->hasAny(['search', 'year', 'status']))
                        <div class="mt-6">
                            <a href="#" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Run Scraper
                            </a>
                        </div>
                    @endif
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($executiveOrders->hasPages())
            <div class="mt-8">
                {{ $executiveOrders->links() }}
            </div>
        @endif
    </div>
</div>

<style>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
@endsection