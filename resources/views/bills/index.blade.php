@extends('layouts.app')

@section('title', 'Congressional Bills')

@section('content')
<!-- Hero Section -->
<div class="bg-gradient-to-r from-slate-800 via-blue-900 to-slate-800 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4" style="font-family: 'Playfair Display', serif;">
                Congressional Bills Database
            </h1>
            <p class="text-xl text-blue-200 mb-6 max-w-3xl mx-auto">
                Track, search, and analyze legislative activity from the U.S. Congress with real-time updates and comprehensive bill information.
            </p>
            <div class="flex justify-center items-center space-x-8 text-sm">
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-green-400 rounded-full animate-pulse"></div>
                    <span>Live Data</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-amber-400 rounded-full"></div>
                    <span>119th Congress</span>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="w-3 h-3 bg-blue-400 rounded-full"></div>
                    <span>Updated {{ now()->format('M j, Y') }}</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @if(isset($error))
        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Statistics Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-white">Total Bills</h3>
                    </div>
                </div>
            </div>
            <div class="px-6 py-6">
                <div class="text-3xl font-bold text-gray-900 mb-2">{{ number_format($stats['total']) }}</div>
                <p class="text-sm text-gray-600">Active legislative proposals</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-white">With Full Text</h3>
                    </div>
                </div>
            </div>
            <div class="px-6 py-6">
                <div class="text-3xl font-bold text-gray-900 mb-2">{{ number_format($stats['with_text']) }}</div>
                <p class="text-sm text-gray-600">{{ $stats['total'] > 0 ? round(($stats['with_text'] / $stats['total']) * 100, 1) : 0 }}% of total bills</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-white">Recent Activity</h3>
                    </div>
                </div>
            </div>
            <div class="px-6 py-6">
                <div class="text-3xl font-bold text-gray-900 mb-2">{{ number_format($stats['recent_actions']) }}</div>
                <p class="text-sm text-gray-600">Actions in last 7 days</p>
            </div>
        </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-100 mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Search & Filter Bills
            </h2>
        </div>
        <form method="GET" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="lg:col-span-2">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Bills</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="{{ $search }}" 
                               placeholder="Search by title, bill number, sponsor, or policy area..."
                               class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                </div>
                
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Bill Type</label>
                    <select name="type" id="type" class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="">All Types</option>
                        <option value="HR" {{ $billType === 'HR' ? 'selected' : '' }}>House Bills (HR)</option>
                        <option value="S" {{ $billType === 'S' ? 'selected' : '' }}>Senate Bills (S)</option>
                        <option value="HRES" {{ $billType === 'HRES' ? 'selected' : '' }}>House Resolutions (HRES)</option>
                        <option value="SRES" {{ $billType === 'SRES' ? 'selected' : '' }}>Senate Resolutions (SRES)</option>
                        <option value="HJRES" {{ $billType === 'HJRES' ? 'selected' : '' }}>House Joint Resolutions (HJRES)</option>
                        <option value="SJRES" {{ $billType === 'SJRES' ? 'selected' : '' }}>Senate Joint Resolutions (SJRES)</option>
                    </select>
                </div>
                
                <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select name="sort" id="sort" class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="latest_action_date" {{ $sortBy === 'latest_action_date' ? 'selected' : '' }}>Latest Action</option>
                        <option value="most_voted" {{ $sortBy === 'most_voted' ? 'selected' : '' }}>Most Voted</option>
                        <option value="introduced_date" {{ $sortBy === 'introduced_date' ? 'selected' : '' }}>Introduced Date</option>
                        <option value="update_date" {{ $sortBy === 'update_date' ? 'selected' : '' }}>Last Updated</option>
                        <option value="title" {{ $sortBy === 'title' ? 'selected' : '' }}>Title</option>
                        <option value="congress_id" {{ $sortBy === 'congress_id' ? 'selected' : '' }}>Bill Number</option>
                    </select>
                </div>
            </div>
            
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mt-6 pt-6 border-t border-gray-200">
                <div class="flex items-center mb-4 sm:mb-0">
                    <label class="flex items-center cursor-pointer">
                        <input type="checkbox" 
                               name="has_text" 
                               value="1" 
                               {{ $hasTextFilter ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded transition-colors">
                        <span class="ml-3 text-sm font-medium text-gray-700">Only show bills with full text available</span>
                    </label>
                </div>
                
                <div class="flex space-x-3">
                    @if($search || $hasTextFilter || $billType || $sortBy !== 'latest_action_date')
                        <a href="{{ route('bills.index') }}" 
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                            <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Clear Filters
                        </a>
                    @endif
                    <button type="submit" 
                            class="inline-flex items-center px-6 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-105">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search Bills
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Header -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">
                @if($search || $hasTextFilter || $billType)
                    Search Results
                @else
                    All Congressional Bills
                @endif
            </h2>
            <p class="text-sm text-gray-600 mt-1">
                Showing {{ $bills->firstItem() ?? 0 }}-{{ $bills->lastItem() ?? 0 }} of {{ number_format($bills->total()) }} bills
            </p>
        </div>
        
        @if($bills->total() > 0)
            <div class="flex items-center space-x-2 text-sm text-gray-500">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>Last updated {{ now()->diffForHumans() }}</span>
            </div>
        @endif
    </div>

    <!-- Bills List -->
    @if($bills->isEmpty())
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-12 text-center">
            <div class="mx-auto w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
                <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No bills found</h3>
            @if($search || $hasTextFilter || $billType)
                <p class="text-gray-600 mb-4">Try adjusting your search criteria to find more results.</p>
                <a href="{{ route('bills.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                    View All Bills
                </a>
            @else
                <p class="text-gray-600">No bills are currently available in the database.</p>
            @endif
        </div>
    @else
        <div class="space-y-4">
            @foreach($bills as $bill)
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 overflow-hidden">
                    <div class="p-6">
                        <div class="flex justify-between items-start">
                            <div class="flex-1 min-w-0">
                                <!-- Bill Header -->
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1">
                                        <h3 class="text-xl font-bold text-gray-900 mb-2 leading-tight">
                                            <a href="{{ route('bills.show', $bill->congress_id) }}" 
                                               class="hover:text-blue-600 transition-colors">
                                                {{ $bill->congress_id }}
                                            </a>
                                        </h3>
                                        <p class="text-gray-700 text-base leading-relaxed mb-3">
                                            {{ $bill->title }}
                                        </p>
                                    </div>
                                    
                                    <!-- Bill Type Badge -->
                                    <div class="ml-4 flex-shrink-0">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                            {{ $bill->type === 'HR' ? 'bg-blue-100 text-blue-800' : 
                                               ($bill->type === 'S' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                            {{ $bill->type }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Bill Details Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    @if($bill->sponsors->isNotEmpty())
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $bill->sponsors->first()->full_name }}</p>
                                                <p class="text-xs text-gray-500">({{ $bill->sponsors->first()->party }}-{{ $bill->sponsors->first()->state }})</p>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if($bill->introduced_date)
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">Introduced</p>
                                                <p class="text-xs text-gray-500">{{ $bill->introduced_date->format('M j, Y') }}</p>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if($bill->policy_area)
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">Policy Area</p>
                                                <p class="text-xs text-gray-500">{{ $bill->policy_area }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Latest Action -->
                                @if($bill->latest_action_text)
                                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                        <div class="flex items-start space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 mb-1">
                                                    Latest Action
                                                    @if($bill->latest_action_date)
                                                        <span class="text-gray-500 font-normal">({{ $bill->latest_action_date->format('M j, Y') }})</span>
                                                    @endif
                                                </p>
                                                <p class="text-sm text-gray-700">{{ Str::limit($bill->latest_action_text, 200) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <!-- Metadata Tags -->
                                <div class="flex flex-wrap gap-2 mb-4">
                                    @if($bill->bill_text)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            Full Text Available
                                        </span>
                                    @endif
                                    
                                    @if($bill->actions_count > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $bill->actions_count }} Actions
                                        </span>
                                    @endif
                                    
                                    @if($bill->cosponsors_count > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            {{ $bill->cosponsors_count }} Cosponsors
                                        </span>
                                    @endif
                                    
                                    @if($bill->summaries_count > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                            {{ $bill->summaries_count }} Summaries
                                        </span>
                                    @endif
                                    
                                    @if($bill->is_fully_scraped)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Complete Data
                                        </span>
                                    @endif
                                    
                                    @php
                                        $totalVotes = $bill->votes()->count();
                                    @endphp
                                    @if($totalVotes > 0)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                     {{ $totalVotes >= 20 ? 'bg-red-100 text-red-800' : ($totalVotes >= 10 ? 'bg-orange-100 text-orange-800' : 'bg-indigo-100 text-indigo-800') }}">
                                            @if($totalVotes >= 20)
                                                <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                </svg>
                                                Popular ({{ $totalVotes }} votes)
                                            @else
                                                <svg class="h-3 w-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3.5M3 16.5h18"></path>
                                                </svg>
                                                {{ $totalVotes }} {{ $totalVotes === 1 ? 'Vote' : 'Votes' }}
                                            @endif
                                        </span>
                                    @endif
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex justify-between items-center">
                                    <div class="flex items-center space-x-3">
                                        <div class="text-xs text-gray-500">
                                            @if($bill->last_scraped_at)
                                                Updated {{ $bill->last_scraped_at->diffForHumans() }}
                                            @endif
                                        </div>
                                        
                                        <!-- Vote Counts (always visible) -->
                                        <div class="flex items-center space-x-1">
                                            @php
                                                $userVote = auth()->user() ? $bill->getUserVote(auth()->user()) : null;
                                                $upvotes = $bill->votes()->where('vote_type', 'up')->count();
                                                $downvotes = $bill->votes()->where('vote_type', 'down')->count();
                                            @endphp
                                            
                                            @auth
                                                <!-- Interactive Voting Buttons for Authenticated Users -->
                                                <button onclick="voteBill('{{ $bill->congress_id }}', 'up')" 
                                                        class="inline-flex items-center px-2 py-1 rounded text-xs font-medium transition-colors
                                                               {{ $userVote === 'up' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-green-50 hover:text-green-600' }}">
                                                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span id="upvotes-{{ $bill->congress_id }}">{{ $upvotes }}</span>
                                                </button>
                                                
                                                <button onclick="voteBill('{{ $bill->congress_id }}', 'down')" 
                                                        class="inline-flex items-center px-2 py-1 rounded text-xs font-medium transition-colors
                                                               {{ $userVote === 'down' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600' }}">
                                                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span id="downvotes-{{ $bill->congress_id }}">{{ $downvotes }}</span>
                                                </button>
                                            @else
                                                <!-- Clickable Vote Display for Non-authenticated Users -->
                                                <a href="{{ route('login') }}" 
                                                   class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600 hover:bg-green-50 hover:text-green-600 transition-colors cursor-pointer"
                                                   title="Login to vote">
                                                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    {{ $upvotes }}
                                                </a>
                                                
                                                <a href="{{ route('login') }}" 
                                                   class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors cursor-pointer"
                                                   title="Login to vote">
                                                    <svg class="h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    {{ $downvotes }}
                                                </a>
                                            @endauth
                                        </div>
                                    </div>
                                    
                                    <a href="{{ route('bills.show', $bill->congress_id) }}" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-105">
                                        View Details
                                        <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Pagination -->
    @if($bills->hasPages())
        <div class="mt-12 bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-700 mb-4 sm:mb-0">
                    Showing <span class="font-medium">{{ $bills->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $bills->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ number_format($bills->total()) }}</span> results
                </div>
                <div>
                    {{ $bills->links() }}
                </div>
            </div>
        </div>
    @endif
</div>

@auth
<script>
// Bill voting functions
async function voteBill(congressId, voteType) {
    try {
        const response = await fetch(`/bills/${congressId}/vote`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ vote_type: voteType })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update vote counts
            document.getElementById(`upvotes-${congressId}`).textContent = data.upvotes;
            document.getElementById(`downvotes-${congressId}`).textContent = data.downvotes;
            
            // Update button styles
            const upButton = document.querySelector(`button[onclick="voteBill('${congressId}', 'up')"]`);
            const downButton = document.querySelector(`button[onclick="voteBill('${congressId}', 'down')"]`);
            
            // Reset both buttons
            upButton.className = upButton.className.replace(/bg-green-100 text-green-700/, 'bg-gray-100 text-gray-600 hover:bg-green-50 hover:text-green-600');
            downButton.className = downButton.className.replace(/bg-red-100 text-red-700/, 'bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600');
            
            // Highlight active vote
            if (data.user_vote === 'up') {
                upButton.className = upButton.className.replace(/bg-gray-100 text-gray-600 hover:bg-green-50 hover:text-green-600/, 'bg-green-100 text-green-700');
            } else if (data.user_vote === 'down') {
                downButton.className = downButton.className.replace(/bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600/, 'bg-red-100 text-red-700');
            }
        } else {
            alert(data.message || 'Failed to record vote');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while voting');
    }
}
</script>
@endauth

@endsection