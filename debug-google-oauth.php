<?php
/**
 * Debug Google OAuth Configuration
 * Run this on Forge to check if Google OAuth is properly configured
 */

echo "=== Google OAuth Configuration Debug ===\n\n";

// Check environment variables
echo "Environment Variables:\n";
echo "GOOGLE_CLIENT_ID: " . (env('GOOGLE_CLIENT_ID') ? 'SET (length: ' . strlen(env('GOOGLE_CLIENT_ID')) . ')' : 'NOT SET') . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (env('GOOGLE_CLIENT_SECRET') ? 'SET (length: ' . strlen(env('GOOGLE_CLIENT_SECRET')) . ')' : 'NOT SET') . "\n";
echo "GOOGLE_REDIRECT_URL: " . (env('GOOGLE_REDIRECT_URL') ?: 'NOT SET') . "\n\n";

// Check config values
echo "Config Values:\n";
echo "services.google.client_id: " . (config('services.google.client_id') ? 'SET (length: ' . strlen(config('services.google.client_id')) . ')' : 'NOT SET') . "\n";
echo "services.google.client_secret: " . (config('services.google.client_secret') ? 'SET (length: ' . strlen(config('services.google.client_secret')) . ')' : 'NOT SET') . "\n";
echo "services.google.redirect: " . (config('services.google.redirect') ?: 'NOT SET') . "\n\n";

// Check if Socialite can access the config
try {
    $driver = app('Laravel\Socialite\Contracts\Factory')->driver('google');
    echo "Socialite Google Driver: LOADED\n";
} catch (Exception $e) {
    echo "Socialite Google Driver: ERROR - " . $e->getMessage() . "\n";
}

echo "\n=== Recommendations ===\n";
if (!config('services.google.client_id')) {
    echo "❌ Google Client ID is not configured\n";
    echo "   Add GOOGLE_CLIENT_ID to your Forge environment variables\n";
}
if (!config('services.google.client_secret')) {
    echo "❌ Google Client Secret is not configured\n";
    echo "   Add GOOGLE_CLIENT_SECRET to your Forge environment variables\n";
}
if (!config('services.google.redirect')) {
    echo "❌ Google Redirect URL is not configured\n";
    echo "   Add GOOGLE_REDIRECT_URL to your Forge environment variables\n";
}

if (config('services.google.client_id') && config('services.google.client_secret') && config('services.google.redirect')) {
    echo "✅ All Google OAuth configuration appears to be set\n";
    echo "   If you're still getting errors, try clearing config cache:\n";
    echo "   php artisan config:clear\n";
    echo "   php artisan config:cache\n";
}