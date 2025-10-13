<?php
/**
 * Google OAuth Setup Script
 * Run this after configuring your Google OAuth credentials
 */

require_once 'vendor/autoload.php';

class GoogleOAuthSetup
{
    public function checkRequirements()
    {
        echo "🔍 Checking Google OAuth Requirements...\n";
        echo str_repeat('-', 50) . "\n";
        
        // Check if Socialite is installed
        if (class_exists('Laravel\Socialite\Facades\Socialite')) {
            echo "✅ Laravel Socialite is installed\n";
        } else {
            echo "❌ Laravel Socialite not found. Run: composer require laravel/socialite\n";
            return false;
        }
        
        // Check environment variables
        $googleClientId = env('GOOGLE_CLIENT_ID');
        $googleClientSecret = env('GOOGLE_CLIENT_SECRET');
        $googleRedirectUrl = env('GOOGLE_REDIRECT_URL');
        
        echo "\nEnvironment Variables:\n";
        echo "  GOOGLE_CLIENT_ID: " . ($googleClientId ? "✅ Set" : "❌ Missing") . "\n";
        echo "  GOOGLE_CLIENT_SECRET: " . ($googleClientSecret ? "✅ Set" : "❌ Missing") . "\n";
        echo "  GOOGLE_REDIRECT_URL: " . ($googleRedirectUrl ?: "❌ Missing") . "\n";
        
        if (!$googleClientId || !$googleClientSecret || !$googleRedirectUrl) {
            echo "\n❌ Please configure Google OAuth credentials in your .env file\n";
            return false;
        }
        
        return true;
    }
    
    public function runMigration()
    {
        echo "\n🔄 Running Google ID Migration...\n";
        echo str_repeat('-', 50) . "\n";
        
        $output = shell_exec('php artisan migrate --force 2>&1');
        echo $output;
    }
    
    public function testConfiguration()
    {
        echo "\n🧪 Testing Google OAuth Configuration...\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            // Test if we can create the Socialite driver
            $driver = \Laravel\Socialite\Facades\Socialite::driver('google');
            echo "✅ Google Socialite driver created successfully\n";
            
            // Test redirect URL generation
            $redirectUrl = $driver->redirect()->getTargetUrl();
            echo "✅ Google OAuth redirect URL generated\n";
            echo "   URL: " . substr($redirectUrl, 0, 80) . "...\n";
            
            return true;
        } catch (\Exception $e) {
            echo "❌ Configuration test failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function showNextSteps()
    {
        echo "\n📝 Next Steps:\n";
        echo str_repeat('=', 50) . "\n";
        echo "1. Set up Google Cloud Console:\n";
        echo "   - Go to https://console.cloud.google.com/\n";
        echo "   - Create OAuth 2.0 credentials\n";
        echo "   - Add redirect URI: " . env('GOOGLE_REDIRECT_URL') . "\n\n";
        echo "2. Update your .env file with:\n";
        echo "   - GOOGLE_CLIENT_ID=your_client_id\n";
        echo "   - GOOGLE_CLIENT_SECRET=your_client_secret\n\n";
        echo "3. Test the login:\n";
        echo "   - Visit /login and click 'Continue with Google'\n";
        echo "   - Visit /register and click 'Sign up with Google'\n\n";
        echo "4. Routes available:\n";
        echo "   - GET /auth/google (redirect to Google)\n";
        echo "   - GET /auth/google/callback (handle callback)\n";
    }
}

// Load Laravel environment if available
if (file_exists('bootstrap/app.php')) {
    try {
        $app = require_once 'bootstrap/app.php';
        $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
    } catch (Exception $e) {
        echo "Note: Could not bootstrap Laravel environment\n";
    }
}

// Run setup
echo "🚀 Google OAuth Setup for GovTrack.Live\n";
echo str_repeat('=', 50) . "\n";

$setup = new GoogleOAuthSetup();

// Step 1: Check requirements
if (!$setup->checkRequirements()) {
    echo "\n❌ Setup failed. Please fix the issues above.\n";
    exit(1);
}

// Step 2: Run migration
$setup->runMigration();

// Step 3: Test configuration
if (!$setup->testConfiguration()) {
    echo "\n⚠️  Configuration test failed, but basic setup is complete.\n";
}

// Step 4: Show next steps
$setup->showNextSteps();

echo "\n✅ Google OAuth setup completed!\n";