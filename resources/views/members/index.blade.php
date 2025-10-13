@extends('layouts.app')

@section('title', 'Members of Congress')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">Members of Congress</h1>
        <p class="text-lg text-gray-600">Browse profiles of current and former members of the U.S. House and Senate</p>
    </div>

    <!-- Search and Filter Section -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-100 mb-8">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 flex items-center">
                <svg class="h-5 w-5 text-gray-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Search & Filter Members
            </h2>
        </div>
        <form method="GET" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-6">
                <!-- Search -->
                <div>
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" 
                           name="search" 
                           id="search" 
                           value="{{ $search }}" 
                           placeholder="Name, state..."
                           class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>

                <!-- Party Filter -->
                <div>
                    <label for="party" class="block text-sm font-medium text-gray-700 mb-2">Party</label>
                    <select name="party" id="party" class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="">All Parties</option>
                        @foreach($parties as $partyOption)
                            <option value="{{ $partyOption }}" {{ $party === $partyOption ? 'selected' : '' }}>
                                {{ $partyOption === 'D' ? 'Democrat' : ($partyOption === 'R' ? 'Republican' : $partyOption) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- State Filter -->
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700 mb-2">State</label>
                    <select name="state" id="state" class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="">All States</option>
                        @foreach($states as $stateOption)
                            <option value="{{ $stateOption }}" {{ $state === $stateOption ? 'selected' : '' }}>{{ $stateOption }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Chamber Filter -->
                <div>
                    <label for="chamber" class="block text-sm font-medium text-gray-700 mb-2">Chamber</label>
                    <select name="chamber" id="chamber" class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="">Both Chambers</option>
                        <option value="house" {{ $chamber === 'house' ? 'selected' : '' }}>House</option>
                        <option value="senate" {{ $chamber === 'senate' ? 'selected' : '' }}>Senate</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                    <select name="sort" id="sort" class="block w-full px-3 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="last_name" {{ $sortBy === 'last_name' ? 'selected' : '' }}>Last Name</option>
                        <option value="first_name" {{ $sortBy === 'first_name' ? 'selected' : '' }}>First Name</option>
                        <option value="state" {{ $sortBy === 'state' ? 'selected' : '' }}>State</option>
                        <option value="party_abbreviation" {{ $sortBy === 'party_abbreviation' ? 'selected' : '' }}>Party</option>
                        <option value="sponsored_legislation_count" {{ $sortBy === 'sponsored_legislation_count' ? 'selected' : '' }}>Bills Sponsored</option>
                        <option value="cosponsored_legislation_count" {{ $sortBy === 'cosponsored_legislation_count' ? 'selected' : '' }}>Bills Cosponsored</option>
                    </select>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center">
                    <input type="checkbox" 
                           name="current_only" 
                           id="current_only" 
                           value="1" 
                           {{ $currentOnly ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="current_only" class="ml-2 text-sm text-gray-700">Current members only</label>
                </div>

                <div class="flex space-x-3">
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                        <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search
                    </button>
                    <a href="{{ route('members.index') }}" class="inline-flex items-center px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 transition-colors">
                        Clear
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Members Grid -->
    @if($members->count() > 0)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            @foreach($members as $member)
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 overflow-hidden hover:shadow-xl transition-shadow">
                    <div class="p-6">
                        <div class="flex items-start space-x-4">
                            <!-- Member Photo -->
                            <div class="flex-shrink-0">
                                @if($member->image_url)
                                    <img src="{{ $member->image_url }}" 
                                         alt="{{ $member->display_name }}"
                                         class="w-16 h-16 rounded-full object-cover border-2 border-gray-200">
                                @else
                                    <div class="w-16 h-16 rounded-full bg-gray-200 flex items-center justify-center">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                    </div>
                                @endif
                            </div>

                            <!-- Member Info -->
                            <div class="flex-1 min-w-0">
                                <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                    <a href="{{ route('members.show', $member->bioguide_id) }}" 
                                       class="hover:text-blue-600 transition-colors">
                                        {{ $member->display_name }}
                                    </a>
                                </h3>
                                <p class="text-sm text-gray-600 mb-2">
                                    {{ $member->party_abbreviation === 'D' ? 'Democrat' : ($member->party_abbreviation === 'R' ? 'Republican' : $member->party_abbreviation) }}
                                    @if($member->state)
                                        - {{ $member->location_display }}
                                    @endif
                                </p>
                                
                                <!-- Stats -->
                                <div class="flex space-x-4 text-xs text-gray-500">
                                    <span>{{ $member->sponsored_legislation_count }} sponsored</span>
                                    <span>{{ $member->cosponsored_legislation_count }} cosponsored</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status Badge -->
                        <div class="mt-4 flex justify-between items-center">
                            <div class="flex space-x-2">
                                @if($member->current_member)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Current
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Former
                                    </span>
                                @endif
                                
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                             {{ $member->party_abbreviation === 'D' ? 'bg-blue-100 text-blue-800' : 
                                                ($member->party_abbreviation === 'R' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                                    {{ $member->party_abbreviation }}
                                </span>
                                
                                @if($member->chamber)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                 {{ $member->chamber === 'house' ? 'bg-purple-100 text-purple-800' : 'bg-indigo-100 text-indigo-800' }}">
                                        {{ $member->chamber === 'house' ? 'House' : 'Senate' }}
                                    </span>
                                @endif
                            </div>
                            
                            <a href="{{ route('members.show', $member->bioguide_id) }}" 
                               class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View Profile →
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($members->hasPages())
            <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-700 mb-4 sm:mb-0">
                        Showing <span class="font-medium">{{ $members->firstItem() ?? 0 }}</span> to <span class="font-medium">{{ $members->lastItem() ?? 0 }}</span> of <span class="font-medium">{{ number_format($members->total()) }}</span> members
                    </div>
                    <div>
                        {{ $members->links() }}
                    </div>
                </div>
            </div>
        @endif
    @else
        <!-- Empty State -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No members found</h3>
            <p class="text-gray-600 mb-4">Try adjusting your search criteria or filters.</p>
            <a href="{{ route('members.index') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                View All Members
            </a>
        </div>
    @endif
</div>
@endsection