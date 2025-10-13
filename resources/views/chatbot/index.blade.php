@extends('layouts.app')

@section('title', 'Congress GPT - AI-Powered Legislative Assistant')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Congress GPT</h1>
            <p class="text-gray-600">Your AI-powered assistant for Congress, bills, members, and legislative insights</p>
            @guest
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <p class="text-blue-800 font-medium">🚀 Ready to explore Congress with AI?</p>
                    <p class="text-blue-600 text-sm mt-1">Sign up for free to start chatting with Congress GPT and get instant insights on legislation, members, and more!</p>
                    <div class="mt-3 space-x-3">
                        <a href="{{ route('register') }}" class="inline-block bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                            Sign Up Free
                        </a>
                        <a href="{{ route('login') }}" class="inline-block text-blue-600 px-6 py-2 rounded-lg font-medium hover:bg-blue-50 transition-colors">
                            Already have an account?
                        </a>
                    </div>
                </div>
            @endguest
        </div>

        <!-- Chat Container -->
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Chat Messages -->
            <div id="chat-messages" class="h-96 overflow-y-auto p-6 space-y-4">
                <!-- Welcome Message -->
                <div class="flex items-start space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="bg-gray-100 rounded-lg p-4">
                            @auth
                                <p class="text-gray-800">
                                    👋 Hello! I'm Congress GPT, your AI-powered legislative assistant. I can help you explore and analyze data about U.S. Congress, including:
                                </p>
                                <ul class="mt-2 text-sm text-gray-600 list-disc list-inside">
                                    <li>Information about specific bills and legislation</li>
                                    <li>Member profiles and activity analysis</li>
                                    <li>Party and state representation insights</li>
                                    <li>Legislative trends and statistics</li>
                                </ul>
                                <p class="mt-2 text-gray-800">What would you like to know about Congress?</p>
                            @else
                                <p class="text-gray-800">
                                    👋 Welcome to Congress GPT! I'm your AI-powered legislative assistant that can help you explore and analyze U.S. Congress data, including:
                                </p>
                                <ul class="mt-2 text-sm text-gray-600 list-disc list-inside">
                                    <li>Real-time information about bills and legislation</li>
                                    <li>Member profiles and voting records</li>
                                    <li>Party and state representation insights</li>
                                    <li>Legislative trends and statistical analysis</li>
                                </ul>
                                <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-blue-800 font-medium text-sm">🔒 Sign up for free to start chatting and get instant AI-powered insights!</p>
                                    <div class="mt-2">
                                        <a href="{{ route('register') }}" class="inline-block bg-blue-600 text-white px-4 py-1.5 rounded text-sm font-medium hover:bg-blue-700 transition-colors">
                                            Create Free Account
                                        </a>
                                    </div>
                                </div>
                            @endauth
                        </div>
                    </div>
                </div>
            </div>

            <!-- Suggestions Panel -->
            <div id="suggestions-panel" class="border-t bg-gray-50 p-4">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Suggested Questions:</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2" id="suggestions-container">
                    <!-- Suggestions will be loaded here -->
                </div>
            </div>

            <!-- Input Area -->
            <div class="border-t p-4">
                @auth
                    <form id="chat-form" class="flex space-x-3" onsubmit="return false;">
                        <input 
                            type="text" 
                            id="message-input" 
                            placeholder="Ask Congress GPT anything about legislation..." 
                            class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent chat-input transition-colors"
                            maxlength="1000"
                            autocomplete="off"
                        >
                        <button 
                            type="submit" 
                            id="send-button"
                            class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span id="send-text">Send</span>
                            <div id="send-spinner" class="hidden">
                                <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </button>
                    </form>
                    <div class="flex justify-between items-center mt-2">
                        <span class="text-xs text-gray-500">Press Enter to send</span>
                        <button 
                            id="clear-chat" 
                            class="text-xs text-gray-500 hover:text-gray-700 transition-colors"
                        >
                            Clear conversation
                        </button>
                    </div>
                @else
                    <div class="text-center py-4">
                        <div class="flex space-x-3 mb-3">
                            <input 
                                type="text" 
                                placeholder="Sign up to start chatting with Congress GPT..." 
                                class="flex-1 border border-gray-300 rounded-lg px-4 py-2 bg-gray-50 cursor-not-allowed"
                                disabled
                            >
                            <button 
                                onclick="window.location.href='{{ route('register') }}'"
                                class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium transition-colors duration-200"
                            >
                                Sign Up
                            </button>
                        </div>
                        <p class="text-xs text-gray-500">Create a free account to start using Congress GPT</p>
                    </div>
                @endauth
            </div>
        </div>

        <!-- Enhanced Analysis Panel -->
        <div id="data-sources" class="mt-6 bg-white rounded-lg shadow-lg p-6 hidden">
            <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Analysis Details
            </h3>
            <div id="sources-list" class="space-y-3"></div>
        </div>
    </div>
</div>

<!-- Typing Indicator -->
<div id="typing-indicator" class="hidden">
    <div class="flex items-start space-x-3">
        <div class="flex-shrink-0">
            <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
            </div>
        </div>
        <div class="flex-1">
            <div class="bg-gray-100 rounded-lg p-4">
                <div class="flex items-center space-x-2">
                    <div class="typing-dots">
                        <div class="dot"></div>
                        <div class="dot"></div>
                        <div class="dot"></div>
                    </div>
                    <span class="text-gray-600 text-sm">Analyzing congressional data...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.typing-dots {
    display: flex;
    align-items: center;
    space-x: 2px;
}

.dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: #6b7280;
    animation: typing 1.4s infinite ease-in-out;
    margin-right: 2px;
}

.dot:nth-child(1) { animation-delay: -0.32s; }
.dot:nth-child(2) { animation-delay: -0.16s; }

@keyframes typing {
    0%, 80%, 100% {
        transform: scale(0.8);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

.typewriter {
    line-height: 1.7;
    word-wrap: break-word;
    white-space: normal;
}

.typewriter p {
    margin: 0.75em 0;
    line-height: 1.7;
}

.typewriter div {
    margin: 0.5em 0;
}

.typewriter h1, .typewriter h2, .typewriter h3, .typewriter h4, .typewriter h5, .typewriter h6 {
    margin: 1.2em 0 0.6em 0;
    font-weight: 600;
    line-height: 1.3;
    color: #1f2937;
}

.typewriter h1 { font-size: 1.5em; }
.typewriter h2 { font-size: 1.3em; }
.typewriter h3 { font-size: 1.15em; }

.typewriter ul, .typewriter ol {
    margin: 0.75em 0;
    padding-left: 0;
}

.typewriter li {
    margin: 0.5em 0;
    line-height: 1.6;
    padding-left: 0.5em;
}

.typewriter strong {
    font-weight: 600;
    color: #1f2937;
}

.typewriter em {
    font-style: italic;
    color: #374151;
}

.typewriter code {
    background-color: #f3f4f6;
    padding: 0.25em 0.5em;
    border-radius: 0.375em;
    font-size: 0.9em;
    font-family: 'Courier New', monospace;
    border: 1px solid #e5e7eb;
}

.typewriter pre {
    background-color: #f9fafb;
    padding: 1em;
    border-radius: 0.5em;
    overflow-x: auto;
    margin: 1em 0;
    border: 1px solid #e5e7eb;
}

.typewriter a {
    color: #2563eb;
    text-decoration: underline;
    font-weight: 500;
}

.typewriter a:hover {
    color: #1d4ed8;
    text-decoration: none;
}

.typewriter br {
    line-height: 1.8;
}

/* Enhanced formatting styles */
.typewriter .bg-gray-50 {
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
}

.typewriter .border-b {
    border-bottom-width: 1px;
}

.typewriter .border-gray-100 {
    border-color: #f3f4f6;
}

.typewriter .text-blue-700 {
    color: #1d4ed8;
}

.typewriter .text-indigo-700 {
    color: #4338ca;
}

.typewriter .font-mono {
    font-family: 'Courier New', monospace;
}

.message-fade-in {
    animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.chat-input:disabled {
    background-color: #f9fafb;
    cursor: not-allowed;
}

.suggestion-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    const messageInput = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const suggestionsPanel = document.getElementById('suggestions-panel');
    const dataSourcesPanel = document.getElementById('data-sources');
    const sourcesListDiv = document.getElementById('sources-list');
    const clearChatButton = document.getElementById('clear-chat');
    const typingIndicator = document.getElementById('typing-indicator');
    
    let conversationId = null;
    let isTyping = false;

    // Load suggestions
    loadSuggestions();

    // Event listeners
    const chatForm = document.getElementById('chat-form');
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        handleSendMessage();
        return false;
    });
    
    sendButton.addEventListener('click', function(e) {
        e.preventDefault();
        handleSendMessage();
        return false;
    });
    
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
            return false;
        }
    });

    clearChatButton.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm('Are you sure you want to clear the conversation?')) {
            clearConversation();
        }
        return false;
    });

    function handleSendMessage() {
        const message = messageInput.value.trim();
        if (message && !isTyping) {
            sendMessage(message);
        }
        return false;
    }

    function sendMessage(message) {
        // Check if user is authenticated
        @guest
            // Redirect to register page if not authenticated
            window.location.href = '{{ route("register") }}';
            return;
        @endguest
        
        // Add user message to chat
        addUserMessage(message);
        
        // Clear input and show loading state
        messageInput.value = '';
        setLoadingState(true);
        showTypingIndicator();

        // Send to backend via AJAX
        fetch('/chatbot/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                message: message,
                conversation_id: conversationId
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            hideTypingIndicator();
            setLoadingState(false);
            
            if (data.success) {
                conversationId = data.conversation_id;
                addAssistantMessage(data.response);
                
                // Show data sources if available
                if (data.data_sources && data.data_sources.length > 0) {
                    showDataSources(data.data_sources, data.analysis_metadata, data.performance_info);
                }
            } else {
                addAssistantMessage('Sorry, I encountered an error processing your question. Please try again.', true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            hideTypingIndicator();
            setLoadingState(false);
            
            let errorMessage = 'Sorry, there was a connection error. Please try again.';
            
            // Check if it's a timeout error
            if (error.name === 'AbortError' || error.message.includes('timeout')) {
                errorMessage = 'The analysis is taking longer than expected. Please try a simpler question or try again.';
            }
            
            // Check if it's a server error
            if (error.message.includes('500')) {
                errorMessage = 'The server encountered an error processing your request. Please try again with a different question.';
            }
            
            addAssistantMessage(errorMessage, true);
        });
    }
    
    function setLoadingState(loading) {
        const sendText = document.getElementById('send-text');
        const sendSpinner = document.getElementById('send-spinner');
        
        sendButton.disabled = loading;
        messageInput.disabled = loading;
        isTyping = loading;
        
        if (loading) {
            sendText.classList.add('hidden');
            sendSpinner.classList.remove('hidden');
        } else {
            sendText.classList.remove('hidden');
            sendSpinner.classList.add('hidden');
        }
    }

    function addUserMessage(content) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-3 justify-end message-fade-in';
        
        messageDiv.innerHTML = `
            <div class="flex-1 max-w-xs sm:max-w-md">
                <div class="bg-blue-500 text-white rounded-lg p-4 shadow-md">
                    <div class="prose prose-sm max-w-none text-white">
                        ${escapeHtml(content)}
                    </div>
                </div>
            </div>
            <div class="flex-shrink-0">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center shadow-sm">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
        `;
        
        chatMessages.appendChild(messageDiv);
        scrollToBottom();
    }

    function addAssistantMessage(content, isError = false) {
        const messageDiv = document.createElement('div');
        messageDiv.className = 'flex items-start space-x-3 message-fade-in';
        
        const bgColor = isError ? 'bg-red-100' : 'bg-gray-100';
        const textColor = isError ? 'text-red-800' : 'text-gray-800';
        const iconColor = isError ? 'bg-red-500' : 'bg-gray-500';
        
        // Create unique ID for this message
        const uniqueId = 'typewriter-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        
        messageDiv.innerHTML = `
            <div class="flex-shrink-0">
                <div class="w-8 h-8 ${iconColor} rounded-full flex items-center justify-center shadow-sm">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                    </svg>
                </div>
            </div>
            <div class="flex-1">
                <div class="${bgColor} rounded-lg p-4 shadow-md">
                    <div class="${textColor} typewriter" id="${uniqueId}">
                    </div>
                </div>
            </div>
        `;
        
        chatMessages.appendChild(messageDiv);
        
        // Start typewriter effect
        const typewriterElement = document.getElementById(uniqueId);
        typewriterEffect(typewriterElement, content);
        
        scrollToBottom();
    }

    function typewriterEffect(element, content, speed = 25) {
        if (!element) {
            console.error('Typewriter element not found');
            return;
        }
        
        // Clear the element
        element.innerHTML = '';
        
        // Check if content has HTML (like bill links)
        const hasHtml = /<[^>]*>/.test(content);
        
        if (hasHtml) {
            // For HTML content, extract text for typing, then apply HTML after
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = content;
            const plainText = tempDiv.textContent || tempDiv.innerText || '';
            
            // Type the plain text first
            typeText(element, plainText, speed, () => {
                // After typing is complete, apply the full HTML formatting
                formatCompletedText(element, content);
                scrollToBottom();
            });
        } else {
            // For plain text, type normally then format
            typeText(element, content, speed, () => {
                formatCompletedText(element, content);
                scrollToBottom();
            });
        }
    }

    function typeText(element, text, speed, callback) {
        const chars = text.split('');
        let currentIndex = 0;
        
        function typeNextChar() {
            if (currentIndex < chars.length) {
                // Add the next character
                const currentText = chars.slice(0, currentIndex + 1).join('');
                element.textContent = currentText;
                
                currentIndex++;
                
                // Vary speed for more natural feel
                const nextSpeed = chars[currentIndex - 1] === ' ' ? speed * 0.3 : 
                                 chars[currentIndex - 1] === '.' ? speed * 2 : speed;
                setTimeout(typeNextChar, nextSpeed);
                
                // Auto-scroll during typing
                if (currentIndex % 50 === 0) {
                    scrollToBottom();
                }
            } else {
                // Typing complete
                if (callback) callback();
            }
        }
        
        typeNextChar();
    }

    function formatCompletedText(element, content) {
        // Check if content already has HTML (like bill links from backend)
        const hasHtml = /<[^>]*>/.test(content);
        
        if (hasHtml) {
            // Content already has HTML formatting, just apply paragraph structure
            let formatted = content;
            
            // Ensure proper paragraph breaks
            formatted = formatted.replace(/\n\s*\n/g, '</p><p class="mb-4 leading-relaxed">');
            
            // Wrap in paragraph if not already wrapped
            if (!formatted.startsWith('<p') && !formatted.startsWith('<h') && !formatted.startsWith('<ul') && !formatted.startsWith('<ol')) {
                formatted = '<p class="mb-4 leading-relaxed">' + formatted + '</p>';
            }
            
            element.innerHTML = formatted;
            return;
        }
        
        // Apply formatting for plain text content
        let formatted = content;
        
        // Split into paragraphs first
        const paragraphs = formatted.split(/\n\s*\n/);
        const formattedParagraphs = [];
        
        paragraphs.forEach(paragraph => {
            paragraph = paragraph.trim();
            if (!paragraph) return;
            
            // Check if it's a header (ends with colon)
            if (paragraph.match(/^.+:$/m)) {
                const lines = paragraph.split('\n');
                const processedLines = lines.map(line => {
                    if (line.trim().endsWith(':')) {
                        return `<h3 class="text-lg font-semibold text-gray-900 mt-4 mb-2">${line.trim()}</h3>`;
                    }
                    return `<p class="mb-2 leading-relaxed">${formatInlineText(line)}</p>`;
                });
                formattedParagraphs.push(processedLines.join('\n'));
                return;
            }
            
            // Check if it contains bullet points
            if (paragraph.match(/^[-•*]\s+/m)) {
                const items = paragraph.split('\n').filter(line => line.trim());
                const listItems = items.map(item => {
                    if (item.match(/^[-•*]\s+/)) {
                        const text = item.replace(/^[-•*]\s+/, '');
                        return `<li class="mb-1">${formatInlineText(text)}</li>`;
                    }
                    return `<p class="mb-2 leading-relaxed">${formatInlineText(item)}</p>`;
                });
                formattedParagraphs.push(`<ul class="list-disc list-inside mb-4 ml-4">${listItems.join('')}</ul>`);
                return;
            }
            
            // Check if it contains numbered lists
            if (paragraph.match(/^\d+\.\s+/m)) {
                const items = paragraph.split('\n').filter(line => line.trim());
                const listItems = items.map(item => {
                    if (item.match(/^\d+\.\s+/)) {
                        const text = item.replace(/^\d+\.\s+/, '');
                        return `<li class="mb-1">${formatInlineText(text)}</li>`;
                    }
                    return `<p class="mb-2 leading-relaxed">${formatInlineText(item)}</p>`;
                });
                formattedParagraphs.push(`<ol class="list-decimal list-inside mb-4 ml-4">${listItems.join('')}</ol>`);
                return;
            }
            
            // Regular paragraph
            formattedParagraphs.push(`<p class="mb-4 leading-relaxed">${formatInlineText(paragraph)}</p>`);
        });
        
        element.innerHTML = formattedParagraphs.join('\n');
    }

    function formatInlineText(text) {
        // Format bold text
        text = text.replace(/\*\*(.*?)\*\*/g, '<strong class="font-semibold text-gray-900">$1</strong>');
        
        // Format numbers and percentages (but avoid formatting if already in HTML tags)
        if (!text.includes('<')) {
            text = text.replace(/\b(\d+(?:,\d{3})*(?:\.\d+)?%?)\b/g, '<span class="font-medium text-blue-700">$1</span>');
        }
        
        return text;
    }

    function showTypingIndicator() {
        const indicator = typingIndicator.cloneNode(true);
        indicator.classList.remove('hidden');
        indicator.id = 'active-typing-indicator';
        chatMessages.appendChild(indicator);
        scrollToBottom();
    }

    function hideTypingIndicator() {
        const activeIndicator = document.getElementById('active-typing-indicator');
        if (activeIndicator) {
            activeIndicator.remove();
        }
    }

    function loadSuggestions() {
        fetch('/chatbot/suggestions')
        .then(response => response.json())
        .then(suggestions => {
            const suggestionsContainer = document.getElementById('suggestions-container');
            suggestionsContainer.innerHTML = '';
            
            Object.entries(suggestions).forEach(([category, questions]) => {
                questions.slice(0, 2).forEach(question => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'text-left text-sm text-blue-600 hover:text-blue-800 hover:bg-blue-50 p-2 rounded border border-blue-200 transition-all duration-200 suggestion-button';
                    button.textContent = question;
                    button.addEventListener('click', (e) => {
                        e.preventDefault();
                        @guest
                            // Redirect to register page if not authenticated
                            window.location.href = '{{ route("register") }}';
                            return false;
                        @endguest
                        if (!isTyping) {
                            messageInput.value = question;
                            sendMessage(question);
                        }
                        return false;
                    });
                    suggestionsContainer.appendChild(button);
                });
            });
        })
        .catch(error => console.error('Error loading suggestions:', error));
    }

    function showDataSources(sources, metadata = {}, performanceInfo = {}) {
        sourcesListDiv.innerHTML = '';
        
        // Add analysis metadata header if available
        if (Object.keys(metadata).length > 0 || Object.keys(performanceInfo).length > 0) {
            const metadataDiv = document.createElement('div');
            metadataDiv.className = 'mb-3 p-3 bg-blue-50 rounded-lg border border-blue-200';
            
            let metadataHtml = '<div class="text-sm font-medium text-blue-800 mb-2">Analysis Details</div>';
            metadataHtml += '<div class="grid grid-cols-2 gap-2 text-xs text-blue-700">';
            
            if (metadata.analyses_performed) {
                metadataHtml += `<div><span class="font-medium">Analyses:</span> ${metadata.analyses_performed}</div>`;
            }
            
            if (metadata.data_points_analyzed) {
                metadataHtml += `<div><span class="font-medium">Data Points:</span> ${metadata.data_points_analyzed.toLocaleString()}</div>`;
            }
            
            if (metadata.data_confidence) {
                metadataHtml += `<div><span class="font-medium">Data Quality:</span> ${metadata.data_confidence}</div>`;
            }
            
            if (performanceInfo.response_speed) {
                metadataHtml += `<div><span class="font-medium">Speed:</span> ${performanceInfo.response_speed}</div>`;
            }
            
            if (performanceInfo.analysis_type) {
                metadataHtml += `<div class="col-span-2"><span class="font-medium">Type:</span> ${performanceInfo.analysis_type}</div>`;
            }
            
            metadataHtml += '</div>';
            metadataDiv.innerHTML = metadataHtml;
            sourcesListDiv.appendChild(metadataDiv);
        }
        
        // Add data sources
        if (sources && sources.length > 0) {
            const sourcesHeader = document.createElement('div');
            sourcesHeader.className = 'text-sm font-medium text-gray-700 mb-2';
            sourcesHeader.textContent = 'Data Sources:';
            sourcesListDiv.appendChild(sourcesHeader);
            
            sources.forEach(source => {
                const sourceDiv = document.createElement('div');
                sourceDiv.className = 'text-sm text-gray-600 bg-gray-50 p-2 rounded mb-1';
                sourceDiv.textContent = source;
                sourcesListDiv.appendChild(sourceDiv);
            });
        }
        
        dataSourcesPanel.classList.remove('hidden');
    }

    function clearConversation() {
        // Clear messages except welcome message
        const messages = chatMessages.querySelectorAll('.flex.items-start.space-x-3');
        for (let i = 1; i < messages.length; i++) {
            messages[i].remove();
        }
        
        // Show suggestions again
        suggestionsPanel.classList.remove('hidden');
        
        // Hide data sources
        dataSourcesPanel.classList.add('hidden');
        
        // Clear conversation on backend
        if (conversationId) {
            fetch('/chatbot/clear', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    conversation_id: conversationId
                })
            });
        }
        
        conversationId = null;
    }

    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endsection