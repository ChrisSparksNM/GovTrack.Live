<?php
/**
 * Test Voyage AI API on Forge server
 */

require_once 'vendor/autoload.php';

// Load Laravel environment
if (file_exists('bootstrap/app.php')) {
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
}

use App\Services\EmbeddingService;
use Illuminate\Support\Facades\Http;

class VoyageForgeTest
{
    public function checkEnvironment()
    {
        echo "🔍 Checking Voyage AI Configuration...\n";
        echo str_repeat('-', 50) . "\n";
        
        $apiKey = env('VOYAGE_API_KEY');
        $baseUrl = env('VOYAGE_API_BASE_URL');
        
        echo "Environment Variables:\n";
        echo "  VOYAGE_API_KEY: " . ($apiKey ? "✅ Set (" . substr($apiKey, 0, 10) . "...)" : "❌ Missing") . "\n";
        echo "  VOYAGE_API_BASE_URL: " . ($baseUrl ?: "Using default") . "\n";
        
        // Check config
        $configKey = config('services.voyage.api_key');
        $configUrl = config('services.voyage.base_url');
        
        echo "\nConfig Values:\n";
        echo "  services.voyage.api_key: " . ($configKey ? "✅ Set (" . substr($configKey, 0, 10) . "...)" : "❌ Missing") . "\n";
        echo "  services.voyage.base_url: {$configUrl}\n";
        
        return !empty($configKey);
    }
    
    public function testDirectAPI()
    {
        echo "\n🧪 Testing Direct API Call...\n";
        echo str_repeat('-', 50) . "\n";
        
        $apiKey = config('services.voyage.api_key');
        $baseUrl = config('services.voyage.base_url');
        
        if (!$apiKey) {
            echo "❌ No API key available for testing\n";
            return false;
        }
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($baseUrl . '/embeddings', [
                'model' => 'voyage-3-large',
                'input' => 'This is a test bill about healthcare reform.',
                'input_type' => 'document'
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $embedding = $data['data'][0]['embedding'] ?? null;
                
                echo "✅ API call successful!\n";
                echo "  Model: " . ($data['model'] ?? 'unknown') . "\n";
                echo "  Embedding dimensions: " . (is_array($embedding) ? count($embedding) : 'unknown') . "\n";
                echo "  Usage tokens: " . ($data['usage']['total_tokens'] ?? 'unknown') . "\n";
                
                return true;
            } else {
                echo "❌ API call failed!\n";
                echo "  Status: " . $response->status() . "\n";
                echo "  Response: " . $response->body() . "\n";
                return false;
            }
            
        } catch (\Exception $e) {
            echo "❌ Exception during API call: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testEmbeddingService()
    {
        echo "\n🔧 Testing EmbeddingService Class...\n";
        echo str_repeat('-', 50) . "\n";
        
        try {
            $embeddingService = new EmbeddingService();
            
            $embedding = $embeddingService->generateEmbedding('This is a test bill about environmental protection.');
            
            if ($embedding) {
                echo "✅ EmbeddingService working!\n";
                echo "  Embedding dimensions: " . count($embedding) . "\n";
                echo "  First few values: " . implode(', ', array_slice($embedding, 0, 5)) . "...\n";
                return true;
            } else {
                echo "❌ EmbeddingService returned null\n";
                return false;
            }
            
        } catch (\Exception $e) {
            echo "❌ EmbeddingService exception: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    public function testGenerateEmbeddingsCommand()
    {
        echo "\n🚀 Testing Generate Embeddings Command...\n";
        echo str_repeat('-', 50) . "\n";
        
        echo "Running: php artisan embeddings:generate --type=bills --limit=1\n";
        $output = shell_exec('php artisan embeddings:generate --type=bills --limit=1 2>&1');
        echo $output;
    }
    
    public function showDatabaseStats()
    {
        echo "\n📊 Database Stats...\n";
        echo str_repeat('-', 50) . "\n";
        
        $output = shell_exec('php artisan db:stats 2>&1');
        echo $output;
    }
}

// Run the test
echo "🚀 Voyage AI Forge Server Test\n";
echo str_repeat('=', 50) . "\n";

$test = new VoyageForgeTest();

// Step 1: Check environment
if (!$test->checkEnvironment()) {
    echo "\n❌ Environment check failed. Please check your .env configuration.\n";
    exit(1);
}

// Step 2: Test direct API
if (!$test->testDirectAPI()) {
    echo "\n❌ Direct API test failed. Please check your API key.\n";
    exit(1);
}

// Step 3: Test embedding service
if (!$test->testEmbeddingService()) {
    echo "\n❌ EmbeddingService test failed.\n";
    exit(1);
}

// Step 4: Show database stats
$test->showDatabaseStats();

// Step 5: Test the command
$test->testGenerateEmbeddingsCommand();

echo "\n✅ All tests completed!\n";