<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'Laravel'))</title>

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-JVJPH17KLK"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-JVJPH17KLK');
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom styles for bill text formatting -->
        <style>
            .bill-title {
                font-family: 'Playfair Display', serif;
                background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .bill-section {
                border-left: 4px solid #e5e7eb;
                padding-left: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .section-header {
                font-family: 'Playfair Display', serif;
                color: #1f2937;
                font-weight: 600;
            }
            
            .bill-subsection {
                border-left: 2px solid #d1d5db;
                padding-left: 0.75rem;
                margin-left: 1rem;
            }
            
            .bill-paragraph {
                line-height: 1.7;
                text-align: justify;
            }
            
            .prose {
                color: #374151;
                line-height: 1.7;
            }
            
            .prose strong {
                color: #1f2937;
                font-weight: 600;
            }
            
            /* AI Summary specific styles */
            .ai-summary-section {
                background: linear-gradient(135deg, #f3e8ff 0%, #dbeafe 100%);
            }
            
            .ai-summary-header {
                background: linear-gradient(135deg, #7c3aed 0%, #2563eb 100%);
            }
            
            .loading-spinner {
                animation: spin 1s linear infinite;
            }
            
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gradient-to-br from-slate-50 via-blue-50 to-slate-100">
            <!-- Presidential Navigation -->
            <nav class="bg-gradient-to-r from-slate-900 via-blue-900 to-slate-900 shadow-2xl border-b-4 border-amber-400">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex justify-between items-center h-20">
                        <!-- Logo Section -->
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0">
                                <div class="flex items-center space-x-3">
                                    <!-- Eagle Icon -->
                                    <div class="w-12 h-12 bg-gradient-to-br from-amber-400 to-amber-600 rounded-full flex items-center justify-center shadow-lg">
                                        <svg class="w-7 h-7 text-slate-900" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                    </div>
                                    <div class="flex flex-col">
                                        <a href="{{ route('bills.index') }}" class="text-2xl font-bold text-white tracking-wide" style="font-family: 'Playfair Display', serif;">
                                            GovTrack.Live
                                        </a>
                                        <span class="text-xs text-amber-300 font-medium tracking-widest uppercase">Legislative Oversight</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Desktop Navigation Links -->
                        <div class="hidden md:flex items-center space-x-1">
                            <a href="{{ route('bills.index') }}" 
                               class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 {{ request()->routeIs('bills.*') ? 'text-amber-300' : 'text-white hover:text-amber-300' }}">
                                <span class="relative z-10">CONGRESSIONAL BILLS</span>
                                @if(request()->routeIs('bills.*'))
                                    <div class="absolute inset-0 bg-white/10 rounded-lg border border-amber-400/30"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>

                            <a href="{{ route('members.index') }}" 
                               class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 {{ request()->routeIs('members.*') ? 'text-amber-300' : 'text-white hover:text-amber-300' }}">
                                <span class="relative z-10">MEMBERS</span>
                                @if(request()->routeIs('members.*'))
                                    <div class="absolute inset-0 bg-white/10 rounded-lg border border-amber-400/30"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>

                            <a href="{{ route('executive-orders.index') }}" 
                               class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 {{ request()->routeIs('executive-orders.*') ? 'text-amber-300' : 'text-white hover:text-amber-300' }}">
                                <span class="relative z-10">EXECUTIVE ORDERS</span>
                                @if(request()->routeIs('executive-orders.*'))
                                    <div class="absolute inset-0 bg-white/10 rounded-lg border border-amber-400/30"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>

                            <a href="{{ route('chatbot.index') }}" 
                               class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 {{ request()->routeIs('chatbot.*') ? 'text-amber-300' : 'text-white hover:text-amber-300' }}">
                                <span class="relative z-10">CONGRESS GPT</span>
                                @guest
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full font-bold">NEW</span>
                                @endguest
                                @if(request()->routeIs('chatbot.*'))
                                    <div class="absolute inset-0 bg-white/10 rounded-lg border border-amber-400/30"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>

                            @auth
                                <a href="{{ route('dashboard') }}" 
                                   class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 {{ request()->routeIs('dashboard') ? 'text-amber-300' : 'text-white hover:text-amber-300' }}">
                                    <span class="relative z-10">MY DASHBOARD</span>
                                    @if(request()->routeIs('dashboard'))
                                        <div class="absolute inset-0 bg-white/10 rounded-lg border border-amber-400/30"></div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                </a>
                            @endauth

                            <!-- Divider -->
                            <div class="h-8 w-px bg-white/20 mx-4"></div>

                            <!-- Auth Links -->
                            @guest
                                <a href="{{ route('login') }}" 
                                   class="group relative px-6 py-3 text-sm font-semibold text-white hover:text-amber-300 tracking-wide transition-all duration-300">
                                    <span class="relative z-10">LOGIN</span>
                                    <div class="absolute inset-0 bg-gradient-to-r from-blue-600/0 via-blue-600/10 to-blue-600/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                </a>
                                
                                <a href="{{ route('register') }}" 
                                   class="group relative px-6 py-3 ml-2 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-900 font-bold text-sm tracking-wide rounded-lg shadow-lg hover:from-amber-400 hover:to-amber-500 transform hover:scale-105 transition-all duration-300 border border-amber-400">
                                    <span class="relative z-10">REGISTER</span>
                                    <div class="absolute inset-0 bg-white/20 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                </a>
                            @else
                                <div class="flex items-center space-x-4">
                                    <span class="text-amber-300 font-medium">Welcome, {{ Auth::user()->name }}</span>
                                    <form method="POST" action="{{ route('logout') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="group relative px-6 py-3 text-sm font-semibold text-white hover:text-red-300 tracking-wide transition-all duration-300">
                                            <span class="relative z-10">LOGOUT</span>
                                            <div class="absolute inset-0 bg-gradient-to-r from-red-600/0 via-red-600/10 to-red-600/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                        </button>
                                    </form>
                                </div>
                            @endguest
                        </div>

                        <!-- Mobile menu button -->
                        <div class="md:hidden">
                            <button type="button" class="mobile-menu-button inline-flex items-center justify-center p-2 rounded-md text-white hover:text-amber-300 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-amber-400 transition-colors duration-300">
                                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path class="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                                    <path class="close-icon hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Mobile Navigation Menu -->
                    <div class="mobile-menu hidden md:hidden border-t border-white/20">
                        <div class="px-2 pt-4 pb-6 space-y-2">
                            <a href="{{ route('bills.index') }}" 
                               class="block px-4 py-3 text-base font-semibold {{ request()->routeIs('bills.*') ? 'text-amber-300 bg-white/10' : 'text-white hover:text-amber-300 hover:bg-white/5' }} rounded-lg transition-all duration-300">
                                Congressional Bills
                            </a>
                            
                            <a href="{{ route('members.index') }}" 
                               class="block px-4 py-3 text-base font-semibold {{ request()->routeIs('members.*') ? 'text-amber-300 bg-white/10' : 'text-white hover:text-amber-300 hover:bg-white/5' }} rounded-lg transition-all duration-300">
                                Members
                            </a>
                            
                            <a href="{{ route('executive-orders.index') }}" 
                               class="block px-4 py-3 text-base font-semibold {{ request()->routeIs('executive-orders.*') ? 'text-amber-300 bg-white/10' : 'text-white hover:text-amber-300 hover:bg-white/5' }} rounded-lg transition-all duration-300">
                                Executive Orders
                            </a>
                            
                            <a href="{{ route('chatbot.index') }}" 
                               class="block px-4 py-3 text-base font-semibold {{ request()->routeIs('chatbot.*') ? 'text-amber-300 bg-white/10' : 'text-white hover:text-amber-300 hover:bg-white/5' }} rounded-lg transition-all duration-300 relative">
                                Congress GPT
                                @guest
                                    <span class="inline-block ml-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full font-bold">NEW</span>
                                @endguest
                            </a>
                            
                            @auth
                                <a href="{{ route('dashboard') }}" 
                                   class="block px-4 py-3 text-base font-semibold {{ request()->routeIs('dashboard') ? 'text-amber-300 bg-white/10' : 'text-white hover:text-amber-300 hover:bg-white/5' }} rounded-lg transition-all duration-300">
                                    My Dashboard
                                </a>
                            @endauth
                            
                            @guest
                                <div class="border-t border-white/20 pt-4 mt-4">
                                    <a href="{{ route('login') }}" 
                                       class="block px-4 py-3 text-base font-semibold text-white hover:text-amber-300 hover:bg-white/5 rounded-lg transition-all duration-300">
                                        Login
                                    </a>
                                    <a href="{{ route('register') }}" 
                                       class="block px-4 py-3 mt-2 text-base font-bold text-slate-900 bg-gradient-to-r from-amber-500 to-amber-600 rounded-lg hover:from-amber-400 hover:to-amber-500 transition-all duration-300">
                                        Register
                                    </a>
                                </div>
                            @else
                                <div class="border-t border-white/20 pt-4 mt-4">
                                    <div class="px-4 py-2 text-amber-300 font-medium">Welcome, {{ Auth::user()->name }}</div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="block w-full text-left px-4 py-3 text-base font-semibold text-white hover:text-red-300 hover:bg-white/5 rounded-lg transition-all duration-300">
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            @endguest
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Add some JavaScript for mobile menu -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const mobileMenuButton = document.querySelector('.mobile-menu-button');
                    const mobileMenu = document.querySelector('.mobile-menu');
                    const menuIcon = document.querySelector('.menu-icon');
                    const closeIcon = document.querySelector('.close-icon');

                    if (mobileMenuButton && mobileMenu) {
                        mobileMenuButton.addEventListener('click', function() {
                            mobileMenu.classList.toggle('hidden');
                            menuIcon.classList.toggle('hidden');
                            closeIcon.classList.toggle('hidden');
                        });
                    }
                });
            </script>

            <!-- Page Heading -->
            @hasSection('header')
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        @yield('header')
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                @yield('content')
            </main>
        </div>
    </body>
</html>
