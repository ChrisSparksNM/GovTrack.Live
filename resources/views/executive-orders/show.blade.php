@extends('layouts.app')

@section('title', 'Executive Order ' . ($executiveOrder->order_number ?? '') . ' - ' . Str::limit($executiveOrder->title ?? 'Executive Order Details', 60))
@section('description', 'Read the full text of Executive Order ' . ($executiveOrder->order_number ?? '') . ': ' . Str::limit(strip_tags($executiveOrder->summary ?? $executiveOrder->title ?? 'Executive Order details'), 150))
@section('keywords', 'executive order, president, white house, ' . strtolower($executiveOrder->order_number ?? '') . ', government, politics, administration')

@section('og_title', 'Executive Order ' . ($executiveOrder->order_number ?? '') . ' - ' . Str::limit($executiveOrder->title ?? 'Executive Order Details', 60))
@section('og_description', 'Read the full text of Executive Order ' . ($executiveOrder->order_number ?? '') . ': ' . Str::limit(strip_tags($executiveOrder->summary ?? $executiveOrder->title ?? 'Executive Order details'), 150))
@section('og_type', 'article')
@section('og_image', asset('images/executive-order-social-card.jpg'))
@section('og_image_alt', 'Executive Order ' . ($executiveOrder->order_number ?? '') . ': ' . Str::limit($executiveOrder->title ?? 'Executive Order', 50))

@section('twitter_title', 'Executive Order ' . ($executiveOrder->order_number ?? '') . ' - ' . Str::limit($executiveOrder->title ?? 'Executive Order Details', 60))
@section('twitter_description', 'Executive Order ' . ($executiveOrder->order_number ?? '') . ': ' . Str::limit(strip_tags($executiveOrder->summary ?? $executiveOrder->title ?? 'Executive Order details'), 180))

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ url('/') }}" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="{{ route('executive-orders.index') }}" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2">
                            Executive Orders
                        </a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-1 text-sm font-medium text-gray-500 md:ml-2">{{ $executiveOrder->display_name }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-6">
            <div class="flex items-start justify-between mb-6">
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-4">
                        @if($executiveOrder->order_number)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                Executive Order {{ $executiveOrder->order_number }}
                            </span>
                        @endif
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                            {{ $executiveOrder->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                            {{ ucfirst($executiveOrder->status) }}
                        </span>
                    </div>
                    
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">
                        {{ $executiveOrder->title }}
                    </h1>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 8a2 2 0 100-4 2 2 0 000 4zm0 0v4a2 2 0 002 2h6a2 2 0 002-2v-4"></path>
                            </svg>
                            <strong>Signed:</strong> {{ $executiveOrder->signed_date->format('F j, Y') }}
                        </div>
                        
                        @if($executiveOrder->hasContent())
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <strong>Length:</strong> {{ number_format($executiveOrder->word_count) }} words
                            </div>
                        @endif
                        
                        @if($executiveOrder->reading_time)
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <strong>Reading Time:</strong> {{ $executiveOrder->reading_time }} minutes
                            </div>
                        @endif
                    </div>
                </div>
                
                <div class="ml-6 flex flex-col space-y-2">
                    @if($executiveOrder->hasContent())
                        <button id="generate-summary-btn" 
                                onclick="generateAISummary('{{ $executiveOrder->slug }}')"
                                class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg font-medium text-sm hover:bg-purple-700 transition-colors focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                            <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                            </svg>
                            <span class="btn-text">
                                @if($executiveOrder->ai_summary)
                                    Regenerate Summary
                                @else
                                    Generate AI Summary
                                @endif
                            </span>
                            <svg class="animate-spin -mr-1 ml-2 h-4 w-4 text-white hidden" id="summary-spinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </button>
                    @endif
                    
                    <a href="{{ $executiveOrder->url }}" 
                       target="_blank"
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg class="mr-2 -ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        View on WhiteHouse.gov
                    </a>
                </div>
            </div>
            
            <!-- Topics -->
            @if($executiveOrder->topics && count($executiveOrder->topics) > 0)
                <div class="border-t pt-6">
                    <h3 class="text-sm font-medium text-gray-900 mb-3">Topics</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($executiveOrder->topics as $topic)
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ $topic }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Summary -->
        @if($executiveOrder->summary)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">Summary</h2>
                <p class="text-gray-700 leading-relaxed">{{ $executiveOrder->summary }}</p>
            </div>
        @endif

        <!-- AI Summary -->
        @if($executiveOrder->hasAiSummary())
            <div class="bg-blue-50 rounded-lg border border-blue-200 p-6 mb-6">
                <div class="flex items-center mb-3">
                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <h2 class="text-lg font-semibold text-blue-900">AI Summary</h2>
                </div>
                @if($executiveOrder->ai_summary_html)
                    <div class="prose prose-blue max-w-none text-blue-800">
                        {!! $executiveOrder->ai_summary_html !!}
                    </div>
                @else
                    <p class="text-blue-800 leading-relaxed">{{ $executiveOrder->ai_summary }}</p>
                @endif
                <div class="mt-3 text-xs text-blue-600">
                    Generated {{ $executiveOrder->ai_summary_generated_at->diffForHumans() }}
                </div>
            </div>
        @endif

        <!-- Full Content -->
        @if($executiveOrder->hasContent())
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Full Text</h2>
                <div class="prose prose-gray max-w-none">
                    <div class="text-gray-700 leading-relaxed">
                        @php
                            // The content should now have preserved formatting from the scraper
                            $content = $executiveOrder->content;
                            
                            // Split into paragraphs based on double line breaks
                            $paragraphs = preg_split('/\n\s*\n/', trim($content));
                            $paragraphs = array_filter(array_map('trim', $paragraphs));
                        @endphp
                        
                        @foreach($paragraphs as $paragraph)
                            @if(trim($paragraph))
                                @php
                                    // Check if this looks like a section header
                                    $isHeader = preg_match('/^(Section \d+\.|Sec\. \d+\.|Executive Order|By the authority vested)/i', trim($paragraph));
                                @endphp
                                
                                @if($isHeader)
                                    <h3 class="text-lg font-semibold text-gray-900 mt-6 mb-3">{{ $paragraph }}</h3>
                                @else
                                    <p class="mb-4 text-justify">{{ $paragraph }}</p>
                                @endif
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <div class="bg-yellow-50 rounded-lg border border-yellow-200 p-6">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-yellow-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    <div>
                        <h3 class="text-sm font-medium text-yellow-800">Content Not Available</h3>
                        <p class="text-sm text-yellow-700 mt-1">
                            The full content of this executive order has not been scraped yet. 
                            <a href="{{ $executiveOrder->url }}" target="_blank" class="underline hover:no-underline">
                                View on WhiteHouse.gov
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Metadata -->
        <div class="mt-8 bg-gray-50 rounded-lg p-4">
            <h3 class="text-sm font-medium text-gray-900 mb-2">Metadata</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs text-gray-600">
                <div>
                    <strong>Created:</strong> {{ $executiveOrder->created_at->format('M j, Y') }}
                </div>
                @if($executiveOrder->last_scraped_at)
                    <div>
                        <strong>Last Scraped:</strong> {{ $executiveOrder->last_scraped_at->format('M j, Y') }}
                    </div>
                @endif
                <div>
                    <strong>Status:</strong> {{ ucfirst($executiveOrder->status) }}
                </div>
                <div>
                    <strong>Fully Scraped:</strong> {{ $executiveOrder->is_fully_scraped ? 'Yes' : 'No' }}
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// AI Summary Generation
async function generateAISummary(slug) {
    const button = document.getElementById('generate-summary-btn');
    const buttonText = button.querySelector('.btn-text');
    const spinner = document.getElementById('summary-spinner');
    
    // Show loading state
    buttonText.textContent = 'Generating...';
    spinner.classList.remove('hidden');
    button.disabled = true;
    
    try {
        const response = await fetch(`/executive-orders/${slug}/summary`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update the AI summary section
            updateAISummarySection(data);
            
            // Update button text
            buttonText.textContent = 'Regenerate Summary';
            
            // Show success message
            showNotification('AI summary generated successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to generate summary', 'error');
            buttonText.textContent = 'Generate AI Summary';
        }
    } catch (error) {
        console.error('Error generating summary:', error);
        showNotification('An error occurred while generating the summary', 'error');
        buttonText.textContent = 'Generate AI Summary';
    } finally {
        // Hide loading state
        spinner.classList.add('hidden');
        button.disabled = false;
    }
}

function updateAISummarySection(data) {
    // Find or create the AI summary section
    let aiSummarySection = document.getElementById('ai-summary-section');
    
    if (!aiSummarySection) {
        // Find the summary section by looking for h2 with "Summary" text
        let summarySection = null;
        const h2Elements = document.querySelectorAll('h2');
        for (let h2 of h2Elements) {
            if (h2.textContent.trim() === 'Summary') {
                summarySection = h2.closest('.bg-white');
                break;
            }
        }
        
        if (summarySection) {
            aiSummarySection = document.createElement('div');
            aiSummarySection.id = 'ai-summary-section';
            aiSummarySection.className = 'bg-blue-50 rounded-lg border border-blue-200 p-6 mb-6';
            summarySection.parentNode.insertBefore(aiSummarySection, summarySection.nextSibling);
        } else {
            // Insert after the header section (the one with p-8 class)
            const headerSection = document.querySelector('.bg-white.rounded-lg.shadow-sm.border.border-gray-200.p-8');
            if (headerSection) {
                aiSummarySection = document.createElement('div');
                aiSummarySection.id = 'ai-summary-section';
                aiSummarySection.className = 'bg-blue-50 rounded-lg border border-blue-200 p-6 mb-6';
                headerSection.parentNode.insertBefore(aiSummarySection, headerSection.nextSibling);
            }
        }
    }
    
    if (aiSummarySection) {
        aiSummarySection.innerHTML = `
            <div class="flex items-center mb-3">
                <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                <h2 class="text-lg font-semibold text-blue-900">AI Summary</h2>
            </div>
            <div class="prose prose-blue max-w-none text-blue-800">
                ${data.summary_html || data.summary}
            </div>
            <div class="mt-3 text-xs text-blue-600">
                Generated ${data.generated_at}
            </div>
        `;
    }
}

function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}
</script>
@endsection