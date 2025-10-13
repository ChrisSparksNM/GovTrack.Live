@extends('layouts.app')

@section('title', $member->display_name . ' - Member Profile')

@section('content')
<div class="container mx-auto px-4 py-8">
    <!-- Member Header -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-100 mb-8">
        <div class="p-8">
            <div class="flex flex-col md:flex-row md:items-start md:space-x-8">
                <!-- Member Photo -->
                <div class="flex-shrink-0 mb-6 md:mb-0">
                    @if($member->image_url)
                        <img src="{{ $member->image_url }}" 
                             alt="{{ $member->display_name }}"
                             class="w-32 h-32 rounded-full object-cover border-4 border-gray-200 mx-auto md:mx-0">
                    @else
                        <div class="w-32 h-32 rounded-full bg-gray-200 flex items-center justify-center mx-auto md:mx-0">
                            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                    @endif
                </div>

                <!-- Member Info -->
                <div class="flex-1 text-center md:text-left">
                    <h1 class="text-4xl font-bold text-gray-900 mb-2">{{ $member->display_name }}</h1>
                    <p class="text-xl text-gray-600 mb-4">
                        {{ $member->title ?? 'Member' }}
                        @if($member->party_abbreviation)
                            ({{ $member->party_abbreviation === 'D' ? 'Democrat' : ($member->party_abbreviation === 'R' ? 'Republican' : $member->party_name) }})
                        @endif
                        @if($member->state)
                            from {{ $member->location_display }}
                        @endif
                    </p>

                    <!-- Status and Info -->
                    <div class="flex flex-wrap justify-center md:justify-start gap-3 mb-6">
                        @if($member->current_member)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Current Member
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                Former Member
                            </span>
                        @endif

                        @if($member->chamber)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                         {{ $member->chamber === 'house' ? 'bg-purple-100 text-purple-800' : 'bg-indigo-100 text-indigo-800' }}">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v8a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2H4zm2 6a2 2 0 104 0 2 2 0 00-4 0zm6 0a2 2 0 104 0 2 2 0 00-4 0z" clip-rule="evenodd"></path>
                                </svg>
                                {{ $member->chamber_display }}
                            </span>
                        @endif

                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                                     {{ $member->party_abbreviation === 'D' ? 'bg-blue-100 text-blue-800' : 
                                        ($member->party_abbreviation === 'R' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') }}">
                            {{ $member->party_name }}
                        </span>

                        @if($member->birth_year)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                Born {{ $member->birth_year }}
                            </span>
                        @endif
                    </div>

                    <!-- Contact Info -->
                    @if($member->office_address || $member->office_phone || $member->official_website_url)
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">Contact Information</h3>
                            <div class="space-y-2 text-sm text-gray-600">
                                @if($member->office_address)
                                    <div class="flex items-start">
                                        <svg class="w-4 h-4 mr-2 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                        <span>{{ $member->office_address }}</span>
                                    </div>
                                @endif
                                @if($member->office_phone)
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        <span>{{ $member->office_phone }}</span>
                                    </div>
                                @endif
                                @if($member->official_website_url)
                                    <div class="flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                        <a href="{{ $member->official_website_url }}" target="_blank" class="text-blue-600 hover:text-blue-800">
                                            Official Website ↗
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Bills Sponsored</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_sponsored']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Bills Cosponsored</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_cosponsored']) }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Recent Sponsored</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['recent_sponsored']) }}</p>
                    <p class="text-xs text-gray-500">Last 6 months</p>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600">Recent Cosponsored</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['recent_cosponsored']) }}</p>
                    <p class="text-xs text-gray-500">Last 6 months</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bills Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Sponsored Bills -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Recent Sponsored Bills</h2>
                <p class="text-sm text-gray-600">Bills introduced by {{ $member->display_name }}</p>
            </div>
            <div class="p-6">
                @if($sponsoredBills->count() > 0)
                    <div class="space-y-4">
                        @foreach($sponsoredBills as $bill)
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-medium text-gray-900">
                                        <a href="{{ route('bills.show', $bill->congress_id) }}" 
                                           class="hover:text-blue-600 transition-colors">
                                            {{ $bill->congress_id }}
                                        </a>
                                    </h3>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $bill->type }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700 mb-2">{{ Str::limit($bill->title, 120) }}</p>
                                <div class="flex justify-between items-center text-xs text-gray-500">
                                    <span>{{ $bill->introduced_date ? $bill->introduced_date->format('M j, Y') : 'Unknown date' }}</span>
                                    @php
                                        $totalVotes = $bill->votes()->count();
                                    @endphp
                                    @if($totalVotes > 0)
                                        <span class="inline-flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3.5M3 16.5h18"></path>
                                            </svg>
                                            {{ $totalVotes }} votes
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($stats['total_sponsored'] > 20)
                        <div class="mt-4 text-center">
                            <p class="text-sm text-gray-600">Showing 20 of {{ number_format($stats['total_sponsored']) }} sponsored bills</p>
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-600">No sponsored bills found</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Cosponsored Bills -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Recent Cosponsored Bills</h2>
                <p class="text-sm text-gray-600">Bills supported by {{ $member->display_name }}</p>
            </div>
            <div class="p-6">
                @if($cosponsoredBills->count() > 0)
                    <div class="space-y-4">
                        @foreach($cosponsoredBills as $bill)
                            <div class="border border-gray-200 rounded-lg p-4 hover:border-green-300 transition-colors">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-medium text-gray-900">
                                        <a href="{{ route('bills.show', $bill->congress_id) }}" 
                                           class="hover:text-blue-600 transition-colors">
                                            {{ $bill->congress_id }}
                                        </a>
                                    </h3>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">
                                        {{ $bill->type }}
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700 mb-2">{{ Str::limit($bill->title, 120) }}</p>
                                <div class="flex justify-between items-center text-xs text-gray-500">
                                    <span>{{ $bill->introduced_date ? $bill->introduced_date->format('M j, Y') : 'Unknown date' }}</span>
                                    @php
                                        $totalVotes = $bill->votes()->count();
                                    @endphp
                                    @if($totalVotes > 0)
                                        <span class="inline-flex items-center">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11m0-5.5a1.5 1.5 0 013 0v3.5M3 16.5h18"></path>
                                            </svg>
                                            {{ $totalVotes }} votes
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($stats['total_cosponsored'] > 20)
                        <div class="mt-4 text-center">
                            <p class="text-sm text-gray-600">Showing 20 of {{ number_format($stats['total_cosponsored']) }} cosponsored bills</p>
                        </div>
                    @endif
                @else
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <p class="text-gray-600">No cosponsored bills found</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection