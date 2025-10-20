<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'GovTrack.Live') }}</title>

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-JVJPH17KLK"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-JVJPH17KLK');
        </script>

        <!-- SEO Meta Tags -->
        <meta name="description" content="Join GovTrack.Live to track congressional bills, executive orders, and legislative activity. Stay informed about government transparency and democratic processes.">
        <meta name="keywords" content="congress, bills, legislation, executive orders, government, politics, tracking, democracy, transparency, login, register">
        <meta name="author" content="GovTrack.Live">
        <meta name="robots" content="index, follow">
        <link rel="canonical" href="{{ url()->current() }}">

        <!-- OpenGraph Meta Tags -->
        <meta property="og:site_name" content="GovTrack.Live">
        <meta property="og:title" content="GovTrack.Live - Legislative Oversight & Government Transparency">
        <meta property="og:description" content="Join GovTrack.Live to track congressional bills, executive orders, and legislative activity. Stay informed about government transparency and democratic processes.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ asset('images/govtrack-social-card.jpg') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="GovTrack.Live - Legislative Oversight Platform">
        <meta property="og:locale" content="en_US">

        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:site" content="@GovTrackLive">
        <meta name="twitter:creator" content="@GovTrackLive">
        <meta name="twitter:title" content="GovTrack.Live - Legislative Oversight & Government Transparency">
        <meta name="twitter:description" content="Join GovTrack.Live to track congressional bills, executive orders, and legislative activity in real-time.">
        <meta name="twitter:image" content="{{ asset('images/govtrack-social-card.jpg') }}">
        <meta name="twitter:image:alt" content="GovTrack.Live - Legislative Oversight Platform">

        <!-- Additional Meta Tags -->
        <meta name="theme-color" content="#1e3a8a">
        <meta name="msapplication-TileColor" content="#1e3a8a">
        <meta name="application-name" content="GovTrack.Live">

        <!-- Favicon and Icons -->
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/govtrack-social-card.jpg') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.ico') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon.ico') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.bunny.net/css?family=playfair-display:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
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
                               class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 text-white hover:text-amber-300">
                                <span class="relative z-10">CONGRESSIONAL BILLS</span>
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>

                            <a href="{{ route('members.index') }}" 
                               class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 text-white hover:text-amber-300">
                                <span class="relative z-10">MEMBERS</span>
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>

                            <a href="{{ route('chatbot.index') }}" 
                               class="group relative px-6 py-3 text-sm font-semibold tracking-wide transition-all duration-300 text-white hover:text-amber-300">
                                <span class="relative z-10">AI ASSISTANT</span>
                                <div class="absolute inset-0 bg-gradient-to-r from-amber-400/0 via-amber-400/10 to-amber-400/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>

                            <!-- Divider -->
                            <div class="h-8 w-px bg-white/20 mx-4"></div>

                            <!-- Auth Links -->
                            <a href="{{ route('login') }}" 
                               class="group relative px-6 py-3 text-sm font-semibold text-white hover:text-amber-300 tracking-wide transition-all duration-300 {{ request()->routeIs('login') ? 'text-amber-300' : '' }}">
                                <span class="relative z-10">LOGIN</span>
                                @if(request()->routeIs('login'))
                                    <div class="absolute inset-0 bg-white/10 rounded-lg border border-amber-400/30"></div>
                                @endif
                                <div class="absolute inset-0 bg-gradient-to-r from-blue-600/0 via-blue-600/10 to-blue-600/0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>
                            
                            <a href="{{ route('register') }}" 
                               class="group relative px-6 py-3 ml-2 bg-gradient-to-r from-amber-500 to-amber-600 text-slate-900 font-bold text-sm tracking-wide rounded-lg shadow-lg hover:from-amber-400 hover:to-amber-500 transform hover:scale-105 transition-all duration-300 border border-amber-400 {{ request()->routeIs('register') ? 'from-amber-400 to-amber-500' : '' }}">
                                <span class="relative z-10">REGISTER</span>
                                <div class="absolute inset-0 bg-white/20 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                            </a>
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
                               class="block px-4 py-3 text-base font-semibold text-white hover:text-amber-300 hover:bg-white/5 rounded-lg transition-all duration-300">
                                Congressional Bills
                            </a>
                            
                            <a href="{{ route('members.index') }}" 
                               class="block px-4 py-3 text-base font-semibold text-white hover:text-amber-300 hover:bg-white/5 rounded-lg transition-all duration-300">
                                Members
                            </a>
                            
                            <a href="{{ route('chatbot.index') }}" 
                               class="block px-4 py-3 text-base font-semibold text-white hover:text-amber-300 hover:bg-white/5 rounded-lg transition-all duration-300">
                                AI Assistant
                            </a>
                            
                            <div class="border-t border-white/20 pt-4 mt-4">
                                <a href="{{ route('login') }}" 
                                   class="block px-4 py-3 text-base font-semibold text-white hover:text-amber-300 hover:bg-white/5 rounded-lg transition-all duration-300 {{ request()->routeIs('login') ? 'text-amber-300 bg-white/10' : '' }}">
                                    Login
                                </a>
                                <a href="{{ route('register') }}" 
                                   class="block px-4 py-3 mt-2 text-base font-bold text-slate-900 bg-gradient-to-r from-amber-500 to-amber-600 rounded-lg hover:from-amber-400 hover:to-amber-500 transition-all duration-300 {{ request()->routeIs('register') ? 'from-amber-400 to-amber-500' : '' }}">
                                    Register
                                </a>
                            </div>
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

            <!-- Login/Register Form Container -->
            <div class="flex flex-col sm:justify-center items-center pt-12 pb-6 px-4">
                <div class="w-full sm:max-w-md px-6 py-8 bg-white shadow-xl overflow-hidden sm:rounded-xl border border-gray-200">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
