@extends('layouts.app')

@section('title', 'Congressional Bills')

@section('meta')
    <!-- Basic SEO Meta Tags -->
    <meta name="description" content="Browse and search all Congressional Bills from the U.S. House and Senate. Track legislation, voting records, and stay informed about government policy.">
    <meta name="keywords" content="congressional bills, congress, house, senate, legislation, government policy, voting records, u.s. congress">
    <meta name="author" content="U.S. Congress">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="{{ request()->url() }}">

    <!-- OpenGraph Meta Tags -->
    <meta property="og:title" content="Congressional Bills - {{ config('app.name') }}">
    <meta property="og:description" content="Browse and search all Congressional Bills from the U.S. House and Senate. Track legislation, voting records, and stay informed about government policy.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="en_US">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Congressional Bills - {{ config('app.name') }}">
    <meta name="twitter:description" content="Browse and search all Congressional Bills from the U.S. House and Senate. Track legislation, voting records, and stay informed about government policy.">
    <meta name="twitter:site" content="@uscongress">

    <!-- JSON-LD Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "CollectionPage",
        "name": "Congressional Bills",
        "description": "Browse and search all Congressional Bills from the U.S. House and Senate. Track legislation, voting records, and stay informed about government policy.",
        "url": {!! json_encode(request()->url()) !!},
        "mainEntity": {
            "@type": "ItemList",
            "name": "Congressional Bills",
            "description": "Collection of Congressional Bills from the U.S. House and Senate"
        },
        "breadcrumb": {
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "Home",
                    "item": {!! json_encode(url('/')) !!}
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": "Congressional Bills",
                    "item": {!! json_encode(request()->url()) !!}
                }
            ]
        },
        "publisher": {
            "@type": "GovernmentOrganization",
            "name": "U.S. Congress",
            "url": "https://www.congress.gov"
        }
    }
    </script>
@endsection

@section('content')
<!-- Hero Section -->
<div class="bg-gradient-to-r from-slate-800 via-blue-900 to-slate-800 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4" style="font-family: 'Playfair Display', serif;">
                Congressional Bills
            </h1>
            <p class="text-xl text-blue-100 max-w-3xl mx-auto">
                Track legislation, monitor voting records, and stay informed about the democratic process
            </p>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @if(isset($error))
        <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700">{{ $error }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Bills List -->
    @if($bills->isEmpty())
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-12 text-center">
            <h3 class="text-xl font-semibold text-gray-900 mb-2">No bills found</h3>
            <p class="text-gray-600">No bills are currently available in the database.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach($bills as $bill)
                <div class="bg-white rounded-xl shadow-lg border border-gray-100 hover:shadow-xl transition-all duration-300 overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
                            <div class="flex-1 min-w-0">
                                <!-- Bill Header -->
                                <div class="flex items-start justify-between mb-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h2 class="text-xl font-bold text-gray-900 truncate">
                                                {{ $bill->congress_id }}
                                            </h2>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $bill->type }}
                                            </span>
                                        </div>
                                        
                                        @if($bill->title)
                                            <h3 class="text-lg font-medium text-gray-800 mb-3 leading-tight">
                                                {{ $bill->title }}
                                            </h3>
                                        @endif
                                    </div>
                                </div>

                                <!-- Bill Details Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    @if($bill->sponsors->isNotEmpty())
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $bill->sponsors->first()->name ?? 'Unknown' }}</p>
                                                <p class="text-xs text-gray-500">Sponsor</p>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if($bill->introduced_date)
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 8a2 2 0 100-4 2 2 0 000 4zm0 0v4a2 2 0 002 2h6a2 2 0 002-2v-4"></path>
                                            </svg>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-900">{{ $bill->introduced_date->format('M j, Y') }}</p>
                                                <p class="text-xs text-gray-500">Introduced</p>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    @if($bill->policy_area)
                                        <div class="flex items-center space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                            </svg>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $bill->policy_area }}</p>
                                                <p class="text-xs text-gray-500">Policy Area</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Latest Action -->
                                @if($bill->latest_action_text)
                                    <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                        <div class="flex items-start space-x-2">
                                            <svg class="h-4 w-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
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
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex flex-col space-y-3 lg:items-end">
                                <a href="{{ route('bills.show', $bill->congress_id) }}" 
                                   class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-lg text-sm font-medium text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-105 w-full sm:w-auto">
                                    View Details
                                    <svg class="ml-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </a>
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
                    Showing {{ $bills->firstItem() }} to {{ $bills->lastItem() }} of {{ $bills->total() }} results
                </div>
                {{ $bills->links() }}
            </div>
        </div>
    @endif
</div>
@endsection