<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Socialite\Facades\Socialite;

class DebugGoogleOAuth extends Command
{
    protected $signature = 'debug:google-oauth';
    protected $description = 'Debug Google OAuth configuration';

    public function handle()
    {
        $this->info('=== Google OAuth Configuration Debug ===');
        $this->newLine();

        // Check environment variables
        $this->info('Environment Variables:');
        $clientId = env('GOOGLE_CLIENT_ID');
        $clientSecret = env('GOOGLE_CLIENT_SECRET');
        $redirectUrl = env('GOOGLE_REDIRECT_URL');

        $this->line('GOOGLE_CLIENT_ID: ' . ($clientId ? 'SET (length: ' . strlen($clientId) . ')' : 'NOT SET'));
        $this->line('GOOGLE_CLIENT_SECRET: ' . ($clientSecret ? 'SET (length: ' . strlen($clientSecret) . ')' : 'NOT SET'));
        $this->line('GOOGLE_REDIRECT_URL: ' . ($redirectUrl ?: 'NOT SET'));
        $this->newLine();

        // Check config values
        $this->info('Config Values:');
        $configClientId = config('services.google.client_id');
        $configClientSecret = config('services.google.client_secret');
        $configRedirect = config('services.google.redirect');

        $this->line('services.google.client_id: ' . ($configClientId ? 'SET (length: ' . strlen($configClientId) . ')' : 'NOT SET'));
        $this->line('services.google.client_secret: ' . ($configClientSecret ? 'SET (length: ' . strlen($configClientSecret) . ')' : 'NOT SET'));
        $this->line('services.google.redirect: ' . ($configRedirect ?: 'NOT SET'));
        $this->newLine();

        // Check if Socialite can access the config
        try {
            $driver = Socialite::driver('google');
            $this->info('Socialite Google Driver: ✅ LOADED');
        } catch (\Exception $e) {
            $this->error('Socialite Google Driver: ❌ ERROR - ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('=== Recommendations ===');

        $hasIssues = false;

        if (!$configClientId) {
            $this->error('❌ Google Client ID is not configured');
            $this->line('   Add GOOGLE_CLIENT_ID to your environment variables');
            $hasIssues = true;
        }

        if (!$configClientSecret) {
            $this->error('❌ Google Client Secret is not configured');
            $this->line('   Add GOOGLE_CLIENT_SECRET to your environment variables');
            $hasIssues = true;
        }

        if (!$configRedirect) {
            $this->error('❌ Google Redirect URL is not configured');
            $this->line('   Add GOOGLE_REDIRECT_URL to your environment variables');
            $hasIssues = true;
        }

        if (!$hasIssues) {
            $this->info('✅ All Google OAuth configuration appears to be set');
            $this->line('   If you\'re still getting errors, try clearing config cache:');
            $this->line('   php artisan config:clear');
            $this->line('   php artisan config:cache');
        }

        return 0;
    }
}