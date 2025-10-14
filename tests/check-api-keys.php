<?php
/**
 * API Key Diagnostic Script for Laravel Forge
 * 
 * This script checks if all required API keys are configured
 */

require_once 'vendor/autoload.php';

class ApiKeyChecker
{
    public function checkAllKeys()
    {
        echo "🔑 API Key Configuration Check\n";
        echo str_repeat('=', 50) . "\n";
        
        $keys = [
            'Congress API' => [
                'env_var' => 'CONGRESS_API_KEY',
                'config' => 'services.congress.api_key',
                'required' => true,
                'description' => 'Required for scraping bills and members'
            ],
            'Voyage AI' => [
                'env_var' => 'VOYAGE_API_KEY', 
                'config' => 'services.voyage.api_key',
                'required' => true,
                'description' => 'Required for generating embeddings'
            ],
            'Anthropic Claude' => [
                'env_var' => 'ANTHROPIC_API_KEY',
                'config' => 'services.anthropic.api_key', 
                'required' => false,
                'description' => 'Optional for enhanced chatbot features'
            ],
            'OpenAI' => [
                'env_var' => 'OPENAI_API_KEY',
                'config' => 'services.openai.api_key',
                'required' => false,
                'description' => 'Optional alternative for embeddings'
            ]
        ];
        
        $allGood = true;
        
        foreach ($keys as $name => $info) {
            $envValue = env($info['env_var']);
            $configValue = config($info['config']);
            
            echo "\n📋 {$name}:\n";
            echo "   Environment Variable: {$info['env_var']}\n";
            echo "   Config Path: {$info['config']}\n";
            echo "   Description: {$info['description']}\n";
            
            if ($envValue) {
                echo "   ✅ Environment: Set (" . substr($envValue, 0, 8) . "...)\n";
            } else {
                echo "   ❌ Environment: Not set\n";
                if ($info['required']) {
                    $allGood = false;
                }
            }
            
            if ($configValue) {
                echo "   ✅ Config: Available (" . substr($configValue, 0, 8) . "...)\n";
            } else {
                echo "   ❌ Config: Not available\n";
                if ($info['required']) {
                    $allGood = false;
                }
            }
            
            if ($info['required'] && !$configValue) {
                echo "   🚨 REQUIRED: This key is needed for core functionality!\n";
            }
        }
        
        echo "\n" . str_repeat('=', 50) . "\n";
        
        if ($allGood) {
            echo "✅ All required API keys are configured!\n";
        } else {
            echo "❌ Some required API keys are missing!\n";
            echo "\n📝 To fix this:\n";
            echo "1. In Laravel Forge, go to your site → Environment\n";
            echo "2. Add the missing environment variables\n";
            echo "3. Redeploy your application\n";
        }
        
        return $allGood;
    }
    
    public function testVoyageConnection()
    {
        echo "\n🧪 Testing Voyage AI Connection...\n";
        
        $apiKey = config('services.voyage.api_key');
        if (!$apiKey) {
            echo "❌ Cannot test - VOYAGE_API_KEY not configured\n";
            return false;
        }
        
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post('https://api.voyageai.com/v1/embeddings', [
                'model' => 'voyage-3-large',
                'input' => 'Test connection',
                'input_type' => 'document'
            ]);
            
            if ($response->successful()) {
                echo "✅ Voyage AI connection successful!\n";
                $data = $response->json();
                $embedding = $data['data'][0]['embedding'] ?? null;
                if ($embedding && is_array($embedding)) {
                    echo "   📊 Received embedding with " . count($embedding) . " dimensions\n";
                }
                return true;
            } else {
                echo "❌ Voyage AI connection failed:\n";
                echo "   Status: " . $response->status() . "\n";
                echo "   Response: " . $response->body() . "\n";
                return false;
            }
        } catch (\Exception $e) {
            echo "❌ Connection error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function showNextSteps()
    {
        echo "\n📋 Next Steps:\n";
        echo str_repeat('-', 30) . "\n";
        echo "1. Set missing API keys in Laravel Forge Environment\n";
        echo "2. Test embedding generation: php artisan embeddings:generate --type=bills --limit=1\n";
        echo "3. Run full embedding generation: php artisan embeddings:generate\n";
        echo "4. Test scraper: php artisan scrape:congress-bills --limit=5\n";
    }
}

// Run the API key checker
$checker = new ApiKeyChecker();

$allConfigured = $checker->checkAllKeys();

if ($allConfigured) {
    $checker->testVoyageConnection();
}

$checker->showNextSteps();

echo "\n🎯 Run this script on your Forge server to diagnose API key issues!\n";