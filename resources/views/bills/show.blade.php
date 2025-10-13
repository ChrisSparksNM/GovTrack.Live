@extends('layouts.app')

@section('title', $bill->title ?? 'Bill Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white rounded-lg shadow-lg p-6">
        <!-- Header -->
        <div class="border-b border-gray-200 pb-6 mb-6">
            <!-- Title Section -->
            <div class="mb-6">
                <div class="flex flex-col lg:flex-row lg:justify-between lg:items-start gap-4">
                    <!-- Left: Title and Bill Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-3">
                            <h1 class="text-3xl font-bold text-gray-800">{{ $bill->congress_id }}</h1>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {{ $bill->type }}
                            </span>
                            @if($bill->is_fully_scraped)
                                <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                    ✓ Complete Data
                                </div>
                            @endif
                        </div>
                        <h2 class="text-xl text-gray-700 leading-relaxed mb-2">{{ $bill->title }}</h2>
                        @if($bill->short_title)
                            <h3 class="text-lg text-gray-500 mb-4">{{ $bill->short_title }}</h3>
                        @endif
                    </div>
                    
                    <!-- Right: Actions -->
                    <div class="flex-shrink-0">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <!-- Vote Buttons -->
                            <div class="flex items-center gap-2">
                                @php
                                    $userVote = auth()->user() ? $bill->getUserVote(auth()->user()) : null;
                                    $upvotes = $bill->votes()->where('vote_type', 'up')->count();
                                    $downvotes = $bill->votes()->where('vote_type', 'down')->count();
                                @endphp
                                
                                @auth
                                    <!-- Interactive Voting Buttons for Authenticated Users -->
                                    <button onclick="voteBill('{{ $bill->congress_id }}', 'up')" 
                                            class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                                                   {{ $userVote === 'up' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-green-50 hover:text-green-600' }}">
                                        <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span id="upvotes-{{ $bill->congress_id }}">{{ $upvotes }}</span>
                                    </button>
                                    
                                    <button onclick="voteBill('{{ $bill->congress_id }}', 'down')" 
                                            class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium transition-colors
                                                   {{ $userVote === 'down' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600' }}">
                                        <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span id="downvotes-{{ $bill->congress_id }}">{{ $downvotes }}</span>
                                    </button>
                                @else
                                    <!-- Vote Display for Non-authenticated Users -->
                                    <a href="{{ route('login') }}" 
                                       class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-green-50 hover:text-green-600 transition-colors"
                                       title="Login to vote">
                                        <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $upvotes }}
                                    </a>
                                    
                                    <a href="{{ route('login') }}" 
                                       class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors"
                                       title="Login to vote">
                                        <svg class="h-4 w-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 012 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        {{ $downvotes }}
                                    </a>
                                @endauth
                            </div>
                            
                            <!-- Tracking Buttons -->
                            @auth
                                <div class="flex items-center gap-2">
                                    @if($bill->isTrackedBy(auth()->user()))
                                        <button onclick="untrackBill('{{ $bill->congress_id }}')"
                                                class="inline-flex items-center px-3 py-2 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors">
                                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                            Untrack
                                        </button>
                                    @else
                                        <button onclick="trackBill('{{ $bill->congress_id }}')"
                                                class="inline-flex items-center px-3 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                                            <svg class="h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path>
                                            </svg>
                                            Track
                                        </button>
                                    @endif
                                    <a href="{{ route('dashboard') }}" 
                                       class="inline-flex items-center px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                                        Dashboard
                                    </a>
                                </div>
                            @else
                                <div class="text-sm text-gray-600">
                                    <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800">Login</a> to track bills
                                </div>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Bill Details -->
            <div class="bg-gray-50 rounded-lg p-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 text-sm">
                    @if($bill->sponsors->isNotEmpty())
                        <div>
                            <span class="font-semibold text-gray-700 block mb-1">Sponsor:</span>
                            <div class="text-gray-600">
                                <a href="{{ route('members.show', $bill->sponsors->first()->bioguide_id) }}" 
                                   class="text-blue-600 hover:text-blue-800 hover:underline font-medium">
                                    {{ $bill->sponsors->first()->full_name }}
                                </a>
                            </div>
                            <div class="text-gray-500 text-xs mt-1">({{ $bill->sponsors->first()->party }}-{{ $bill->sponsors->first()->state }})</div>
                        </div>
                    @endif
                    
                    @if($bill->introduced_date)
                        <div>
                            <span class="font-semibold text-gray-700 block mb-1">Introduced:</span>
                            <div class="text-gray-600">{{ $bill->introduced_date->format('M j, Y') }}</div>
                        </div>
                    @endif
                    
                    @if($bill->policy_area)
                        <div>
                            <span class="font-semibold text-gray-700 block mb-1">Policy Area:</span>
                            <div class="text-gray-600">{{ $bill->policy_area }}</div>
                        </div>
                    @endif
                    
                    @if($bill->legislation_url)
                        <div>
                            <span class="font-semibold text-gray-700 block mb-1">Congress.gov:</span>
                            <div>
                                <a href="{{ $bill->legislation_url }}" target="_blank" 
                                   class="inline-flex items-center text-blue-600 hover:text-blue-800 hover:underline">
                                    View on Congress.gov
                                    <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Bill Statistics</h4>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div class="text-center p-3 bg-blue-50 rounded-lg">
                        <div class="text-2xl font-bold text-blue-600">{{ $stats['total_actions'] }}</div>
                        <div class="text-xs text-gray-600 mt-1">Actions</div>
                    </div>
                    <div class="text-center p-3 bg-green-50 rounded-lg">
                        <div class="text-2xl font-bold text-green-600">{{ $stats['total_cosponsors'] }}</div>
                        <div class="text-xs text-gray-600 mt-1">Cosponsors</div>
                    </div>
                    <div class="text-center p-3 bg-purple-50 rounded-lg">
                        <div class="text-2xl font-bold text-purple-600">{{ $stats['total_summaries'] }}</div>
                        <div class="text-xs text-gray-600 mt-1">Summaries</div>
                    </div>
                    <div class="text-center p-3 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600">{{ $stats['total_subjects'] }}</div>
                        <div class="text-xs text-gray-600 mt-1">Subjects</div>
                    </div>
                    <div class="text-center p-3 bg-red-50 rounded-lg">
                        <div class="text-2xl font-bold text-red-600">{{ $stats['total_text_versions'] }}</div>
                        <div class="text-xs text-gray-600 mt-1">Text Versions</div>
                    </div>
                    <div class="text-center p-3 bg-gray-50 rounded-lg">
                        <div class="text-2xl font-bold text-gray-600">{{ $stats['has_full_text'] ? 'Yes' : 'No' }}</div>
                        <div class="text-xs text-gray-600 mt-1">Full Text</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Summary Section -->
        @if($bill->bill_text)
            <div class="mb-6">
                <div class="bg-gradient-to-r from-purple-50 to-blue-50 rounded-xl border border-purple-200 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-blue-600 px-4 sm:px-6 py-4">
                        <!-- Mobile Layout -->
                        <div class="block sm:hidden">
                            <div class="flex items-center mb-3">
                                <svg class="h-5 w-5 text-white mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                                <h3 class="text-lg font-bold text-white">AI Summary</h3>
                            </div>
                            @if($bill->ai_summary)
                                <div class="mb-3">
                                    <span class="px-2 py-1 bg-white/20 text-white text-xs rounded-full">
                                        Generated {{ $bill->ai_summary_generated_at->diffForHumans() }}
                                    </span>
                                </div>
                            @endif
                            <button id="generate-summary-btn" 
                                    onclick="generateAISummary('{{ $bill->congress_id }}')"
                                    class="w-full px-3 py-2 bg-white text-purple-600 rounded-lg font-medium text-sm hover:bg-purple-50 transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50">
                                <span class="btn-text">
                                    @if($bill->ai_summary)
                                        Regenerate Summary
                                    @else
                                        Generate AI Summary
                                    @endif
                                </span>
                                <svg class="loading-spinner hidden inline w-4 h-4 ml-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Desktop Layout -->
                        <div class="hidden sm:flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="h-6 w-6 text-white mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                </svg>
                                <h3 class="text-xl font-bold text-white">AI Summary</h3>
                            </div>
                            <div class="flex items-center space-x-2">
                                @if($bill->ai_summary)
                                    <span class="px-3 py-1 bg-white/20 text-white text-xs rounded-full">
                                        Generated {{ $bill->ai_summary_generated_at->diffForHumans() }}
                                    </span>
                                @endif
                                <button id="generate-summary-btn-desktop" 
                                        onclick="generateAISummary('{{ $bill->congress_id }}')"
                                        class="px-4 py-2 bg-white text-purple-600 rounded-lg font-semibold text-sm hover:bg-purple-50 transition-colors focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 whitespace-nowrap">
                                    <span class="btn-text">
                                        @if($bill->ai_summary)
                                            Regenerate Summary
                                        @else
                                            Generate AI Summary
                                        @endif
                                    </span>
                                    <svg class="loading-spinner hidden inline w-4 h-4 ml-2 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div id="ai-summary-content">
                            @if($bill->ai_summary)
                                <div class="prose prose-sm max-w-none text-gray-700">
                                    @php
                                        // Use HTML version if available, otherwise convert markdown to HTML
                                        $summaryHtml = $bill->ai_summary_html ?? app('App\Services\AnthropicService')->convertMarkdownToHtml($bill->ai_summary);
                                    @endphp
                                    {!! $summaryHtml !!}
                                </div>
                                @if($bill->ai_summary_metadata)
                                    <div class="mt-4 pt-4 border-t border-gray-200 text-xs text-gray-500">
                                        <div class="flex flex-wrap gap-4">
                                            <span>Model: {{ $bill->ai_summary_metadata['model'] ?? 'Claude' }}</span>
                                            @if(isset($bill->ai_summary_metadata['input_tokens']))
                                                <span>Input tokens: {{ number_format($bill->ai_summary_metadata['input_tokens']) }}</span>
                                            @endif
                                            @if(isset($bill->ai_summary_metadata['output_tokens']))
                                                <span>Output tokens: {{ number_format($bill->ai_summary_metadata['output_tokens']) }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="text-center py-8">
                                    <div class="text-gray-500 mb-4">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                        </svg>
                                    </div>
                                    <h4 class="text-lg font-medium text-gray-900 mb-2">No AI Summary Available</h4>
                                    <p class="text-gray-600 mb-4">Click the button above to generate an AI-powered summary of this bill using Claude.</p>
                                    <p class="text-sm text-gray-500">The summary will analyze the bill's key provisions, impact, and implementation details.</p>
                                </div>
                            @endif
                        </div>
                        
                        <!-- Error message container -->
                        <div id="ai-summary-error" class="hidden mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                            <div class="flex">
                                <svg class="h-5 w-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div>
                                    <h4 class="text-sm font-medium text-red-800">Error generating summary</h4>
                                    <p class="text-sm text-red-700 mt-1" id="ai-summary-error-message"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- Latest Action -->
        @if($bill->latest_action_text)
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Latest Action</h3>
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-sm text-blue-800 mb-1">
                        @if($bill->latest_action_date)
                            {{ $bill->latest_action_date->format('M j, Y') }}
                        @endif
                        @if($bill->latest_action_time)
                            at {{ $bill->latest_action_time->format('g:i A') }}
                        @endif
                    </div>
                    <div class="text-gray-700">{{ $bill->latest_action_text }}</div>
                </div>
            </div>
        @endif

        <!-- Summaries -->
        @if($bill->summaries->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Summaries ({{ $bill->summaries->count() }})</h3>
                <div class="space-y-4">
                    @foreach($bill->summaries as $summary)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 mb-2">
                                <strong>{{ $summary->action_desc ?? 'Summary' }}</strong>
                                @if($summary->action_date)
                                    - {{ $summary->action_date->format('M j, Y') }}
                                @endif
                                @if($summary->version_code)
                                    <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                                        {{ $summary->version_code }}
                                    </span>
                                @endif
                            </div>
                            <div class="text-gray-700">{!! nl2br(e($summary->text ?? '')) !!}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Actions -->
        @if($bill->actions->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">
                    Actions ({{ $bill->actions->count() }}@if($stats['total_actions'] > $bill->actions->count()) of {{ $stats['total_actions'] }}@endif)
                </h3>
                <div class="bg-gray-50 p-4 rounded-lg max-h-96 overflow-y-auto">
                    <div class="space-y-3">
                        @foreach($bill->actions as $action)
                            <div class="flex justify-between items-start border-b border-gray-200 pb-2 last:border-b-0">
                                <div class="flex-1">
                                    <div class="text-sm text-gray-700">{{ $action->text }}</div>
                                    @if($action->type || $action->source_system)
                                        <div class="text-xs text-gray-500 mt-1">
                                            @if($action->type)
                                                Type: {{ $action->type }}
                                            @endif
                                            @if($action->source_system)
                                                @if($action->type) | @endif
                                                Source: {{ $action->source_system }}
                                            @endif
                                            @if($action->action_code)
                                                | Code: {{ $action->action_code }}
                                            @endif
                                        </div>
                                    @endif
                                </div>
                                <div class="text-xs text-gray-500 ml-4 text-right">
                                    @if($action->action_date)
                                        {{ $action->action_date->format('M j, Y') }}
                                    @endif
                                    @if($action->action_time)
                                        <br>{{ $action->action_time->format('g:i A') }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($stats['total_actions'] > $bill->actions->count())
                        <div class="text-center mt-3 text-sm text-gray-500">
                            Showing latest {{ $bill->actions->count() }} actions
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Subjects -->
        @if($bill->subjects->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Subjects ({{ $bill->subjects->count() }})</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($bill->subjects as $subject)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm 
                            {{ $subject->type === 'policy_area' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ $subject->name }}
                            @if($subject->type === 'policy_area')
                                <span class="ml-1 text-xs">(Policy Area)</span>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Cosponsors -->
        @if($bill->cosponsors->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">
                    Cosponsors ({{ $bill->cosponsors->count() }}@if($stats['total_cosponsors'] > $bill->cosponsors->count()) of {{ $stats['total_cosponsors'] }}@endif)
                </h3>
                <div class="bg-gray-50 p-4 rounded-lg max-h-64 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($bill->cosponsors as $cosponsor)
                            <div class="text-sm">
                                <div class="font-medium text-gray-800">
                                    <a href="{{ route('members.show', $cosponsor->bioguide_id) }}" 
                                       class="text-blue-600 hover:text-blue-800 hover:underline">
                                        {{ $cosponsor->full_name }}
                                    </a>
                                </div>
                                <div class="text-gray-600">
                                    ({{ $cosponsor->party }}-{{ $cosponsor->state }}@if($cosponsor->district)-{{ $cosponsor->district }}@endif)
                                    @if($cosponsor->sponsorship_date)
                                        <br><span class="text-xs text-gray-500">{{ $cosponsor->sponsorship_date->format('M j, Y') }}</span>
                                    @endif
                                    @if($cosponsor->is_original_cosponsor)
                                        <br><span class="text-xs text-blue-600">Original Cosponsor</span>
                                    @endif
                                    @if($cosponsor->sponsorship_withdrawn_date)
                                        <br><span class="text-xs text-red-600">Withdrawn: {{ $cosponsor->sponsorship_withdrawn_date->format('M j, Y') }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @if($stats['total_cosponsors'] > $bill->cosponsors->count())
                        <div class="text-center mt-3 text-sm text-gray-500">
                            Showing latest {{ $bill->cosponsors->count() }} cosponsors
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <!-- Text Versions -->
        @if($bill->textVersions->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-3">Text Versions ({{ $bill->textVersions->count() }})</h3>
                <div class="space-y-3">
                    @foreach($bill->textVersions as $version)
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h4 class="font-medium text-gray-800">{{ $version->type }}</h4>
                                    @if($version->date)
                                        <div class="text-sm text-gray-600">{{ $version->date->format('M j, Y') }}</div>
                                    @endif
                                </div>
                                @if($version->is_current)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Current
                                    </span>
                                @endif
                            </div>
                            
                            <div class="flex flex-wrap gap-2">
                                @if($version->pdf_url)
                                    <a href="{{ $version->pdf_url }}" 
                                       target="_blank" 
                                       class="inline-flex items-center px-3 py-1 rounded text-sm font-medium bg-red-100 text-red-800 hover:bg-opacity-80">
                                        PDF ↗
                                    </a>
                                @endif
                                @if($version->xml_url)
                                    <a href="{{ $version->xml_url }}" 
                                       target="_blank" 
                                       class="inline-flex items-center px-3 py-1 rounded text-sm font-medium bg-blue-100 text-blue-800 hover:bg-opacity-80">
                                        XML ↗
                                    </a>
                                @endif
                                @if($version->html_url)
                                    <a href="{{ $version->html_url }}" 
                                       target="_blank" 
                                       class="inline-flex items-center px-3 py-1 rounded text-sm font-medium bg-green-100 text-green-800 hover:bg-opacity-80">
                                        HTML ↗
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Full Bill Text -->
        @if($bill->bill_text)
            <div class="mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold text-gray-900">Full Bill Text</h3>
                    <div class="flex space-x-2">
                        <button onclick="toggleTextView()" 
                                class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors">
                            Toggle View
                        </button>
                        <button onclick="copyBillText()" 
                                class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 transition-colors">
                            Copy Text
                        </button>
                    </div>
                </div>
                
                <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
                    <!-- Text Metadata -->
                    <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 text-sm text-gray-600">
                        <div class="flex flex-wrap gap-4">
                            <span><strong>Length:</strong> {{ number_format($stats['text_length']) }} characters</span>
                            @if($bill->bill_text_version_type)
                                <span><strong>Version:</strong> {{ $bill->bill_text_version_type }}</span>
                            @endif
                            @if($bill->bill_text_date)
                                <span><strong>Version Date:</strong> {{ $bill->bill_text_date->format('M j, Y') }}</span>
                            @endif
                            @if($bill->last_scraped_at)
                                <span><strong>Last Updated:</strong> {{ $bill->last_scraped_at->format('M j, Y g:i A') }}</span>
                            @endif
                        </div>
                    </div>
                    
                    <!-- Formatted Text View (Default) -->
                    <div id="formatted-text" class="p-6 max-h-96 overflow-y-auto">
                        <div class="prose prose-sm max-w-none">
                            {!! $bill->formatted_bill_text !!}
                        </div>
                    </div>
                    
                    <!-- Raw Text View (Hidden by default) -->
                    <div id="raw-text" class="p-6 max-h-96 overflow-y-auto hidden">
                        <pre class="whitespace-pre-wrap text-sm text-gray-700 font-mono leading-relaxed">{{ $bill->bill_text }}</pre>
                    </div>
                </div>
                
                @if($bill->bill_text_source_url)
                    <div class="mt-3 text-sm text-gray-500">
                        <a href="{{ $bill->bill_text_source_url }}" 
                           target="_blank" 
                           class="text-blue-600 hover:text-blue-800 underline">
                            View original source ↗
                        </a>
                    </div>
                @endif
            </div>

            <!-- JavaScript for text view functionality -->
            <script>
                function toggleTextView() {
                    const formattedText = document.getElementById('formatted-text');
                    const rawText = document.getElementById('raw-text');
                    
                    if (formattedText.classList.contains('hidden')) {
                        formattedText.classList.remove('hidden');
                        rawText.classList.add('hidden');
                    } else {
                        formattedText.classList.add('hidden');
                        rawText.classList.remove('hidden');
                    }
                }
                
                function copyBillText() {
                    const textToCopy = document.querySelector('#raw-text pre').textContent;
                    navigator.clipboard.writeText(textToCopy).then(function() {
                        // Show a temporary success message
                        const button = event.target;
                        const originalText = button.textContent;
                        button.textContent = 'Copied!';
                        button.classList.add('bg-green-100', 'text-green-700');
                        button.classList.remove('bg-gray-100', 'text-gray-700');
                        
                        setTimeout(function() {
                            button.textContent = originalText;
                            button.classList.remove('bg-green-100', 'text-green-700');
                            button.classList.add('bg-gray-100', 'text-gray-700');
                        }, 2000);
                    });
                }
                
                // AI Summary Generation
                async function generateAISummary(congressId) {
                    // Get both mobile and desktop buttons
                    const mobileButton = document.getElementById('generate-summary-btn');
                    const desktopButton = document.getElementById('generate-summary-btn-desktop');
                    const contentDiv = document.getElementById('ai-summary-content');
                    const errorDiv = document.getElementById('ai-summary-error');
                    
                    // Hide any previous errors
                    errorDiv.classList.add('hidden');
                    
                    // Show loading state on both buttons
                    [mobileButton, desktopButton].forEach(button => {
                        if (button) {
                            const buttonText = button.querySelector('.btn-text');
                            const spinner = button.querySelector('.loading-spinner');
                            
                            button.disabled = true;
                            buttonText.textContent = 'Generating...';
                            spinner.classList.remove('hidden');
                        }
                    });
                    
                    try {
                        const response = await fetch(`/bills/${congressId}/summary`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Update the content with the new summary
                            const summaryHtml = `
                                <div class="prose prose-sm max-w-none text-gray-700">
                                    ${data.summary_html || data.summary.replace(/\n/g, '<br>')}
                                </div>
                                <div class="mt-4 pt-4 border-t border-gray-200 text-xs text-gray-500">
                                    <div class="flex flex-wrap gap-4">
                                        <span>Generated: ${data.generated_at}</span>
                                        <span>Model: Claude 3 Sonnet</span>
                                        ${data.usage ? `
                                            <span>Input tokens: ${data.usage.input_tokens.toLocaleString()}</span>
                                            <span>Output tokens: ${data.usage.output_tokens.toLocaleString()}</span>
                                        ` : ''}
                                        ${data.cached ? '<span class="text-blue-600">Cached</span>' : '<span class="text-green-600">Fresh</span>'}
                                    </div>
                                </div>
                            `;
                            contentDiv.innerHTML = summaryHtml;
                            
                            // Update button text on both buttons
                            [mobileButton, desktopButton].forEach(button => {
                                if (button) {
                                    const buttonText = button.querySelector('.btn-text');
                                    buttonText.textContent = 'Regenerate Summary';
                                }
                            });
                            
                            // Show success message briefly
                            if (!data.cached) {
                                [mobileButton, desktopButton].forEach(button => {
                                    if (button) {
                                        const buttonText = button.querySelector('.btn-text');
                                        const originalBtnText = buttonText.textContent;
                                        buttonText.textContent = 'Summary Generated!';
                                        button.classList.add('bg-green-100', 'text-green-700');
                                        button.classList.remove('bg-white', 'text-purple-600');
                                        
                                        setTimeout(() => {
                                            buttonText.textContent = originalBtnText;
                                            button.classList.remove('bg-green-100', 'text-green-700');
                                            button.classList.add('bg-white', 'text-purple-600');
                                        }, 3000);
                                    }
                                });
                            }
                            
                        } else {
                            // Show error message
                            document.getElementById('ai-summary-error-message').textContent = data.message || 'Unknown error occurred';
                            errorDiv.classList.remove('hidden');
                        }
                        
                    } catch (error) {
                        console.error('Error generating AI summary:', error);
                        document.getElementById('ai-summary-error-message').textContent = 'Network error. Please check your connection and try again.';
                        errorDiv.classList.remove('hidden');
                    } finally {
                        // Reset button state on both buttons
                        [mobileButton, desktopButton].forEach(button => {
                            if (button) {
                                const buttonText = button.querySelector('.btn-text');
                                const spinner = button.querySelector('.loading-spinner');
                                
                                button.disabled = false;
                                spinner.classList.add('hidden');
                                if (buttonText.textContent === 'Generating...') {
                                    buttonText.textContent = 'Generate AI Summary';
                                }
                            }
                        });
                    }
                }

                // Bill tracking functions
                async function trackBill(congressId) {
                    try {
                        const response = await fetch(`/bills/${congressId}/track`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Failed to track bill');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while tracking the bill');
                    }
                }

                async function untrackBill(congressId) {
                    if (!confirm('Are you sure you want to stop tracking this bill?')) {
                        return;
                    }

                    try {
                        const response = await fetch(`/bills/${congressId}/untrack`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            }
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'Failed to untrack bill');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred while untracking the bill');
                    }
                }
            </script>
        @endif

        <!-- Navigation -->
        <div class="flex flex-col sm:flex-row justify-between items-stretch sm:items-center gap-3 pt-6 border-t border-gray-200">
            <a href="{{ route('bills.index') }}" 
               class="group inline-flex items-center justify-center sm:justify-start px-4 py-2.5 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 text-sm font-medium">
                <svg class="w-4 h-4 mr-2 transition-transform group-hover:-translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
                Back to Bills
            </a>
            
            @if($bill->legislation_url)
                <a href="{{ $bill->legislation_url }}" 
                   target="_blank" 
                   class="group inline-flex items-center justify-center sm:justify-start px-4 py-2.5 text-white bg-blue-600 border border-blue-600 rounded-lg hover:bg-blue-700 hover:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 text-sm font-medium shadow-sm">
                    <span class="mr-2">View on Congress.gov</span>
                    <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                </a>
            @endif
        </div>
    </div>
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

// Bill tracking functions (existing)
async function trackBill(congressId) {
    try {
        const response = await fetch(`/bills/${congressId}/track`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to track bill');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while tracking the bill');
    }
}

async function untrackBill(congressId) {
    if (!confirm('Are you sure you want to stop tracking this bill?')) {
        return;
    }

    try {
        const response = await fetch(`/bills/${congressId}/untrack`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Failed to untrack bill');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while untracking the bill');
    }
}
</script>
@endauth

@endsection