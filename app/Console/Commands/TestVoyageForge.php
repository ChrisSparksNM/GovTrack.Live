<?php

namespace App\Console\Commands;

use App\Services\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestVoyageForge extends Command
{
    protected $signature = 'test:voyage-forge';
    protected $description = 'Test Voyage AI API configuration on Forge server';

    public function handle()
    {
        $this->info('🚀 Testing Voyage AI on Forge Server');
        $this->line(str_repeat('=', 50));

        // Step 1: Check environment
        $this->checkEnvironment();

        // Step 2: Test direct API
        if (!$this->testDirectAPI()) {
            $this->error('❌ Direct API test failed. Stopping here.');
            return 1;
        }

        // Step 3: Test embedding service
        if (!$this->testEmbeddingService()) {
            $this->error('❌ EmbeddingService test failed. Stopping here.');
            return 1;
        }

        // Step 4: Test with a real bill
        $this->testWithRealBill();

        $this->info('✅ All Voyage AI tests passed!');
        return 0;
    }

    private function checkEnvironment()
    {
        $this->info('🔍 Checking Voyage AI Configuration...');
        $this->line(str_repeat('-', 50));

        $apiKey = config('services.voyage.api_key');
        $baseUrl = config('services.voyage.base_url');

        $this->line('Environment Variables:');
        $this->line('  VOYAGE_API_KEY: ' . ($apiKey ? '✅ Set (' . substr($apiKey, 0, 10) . '...)' : '❌ Missing'));
        $this->line('  VOYAGE_API_BASE_URL: ' . ($baseUrl ?: 'Using default'));

        if (!$apiKey) {
            $this->error('❌ Voyage API key not configured!');
            $this->line('Make sure VOYAGE_API_KEY is set in your .env file');
            return false;
        }

        return true;
    }

    private function testDirectAPI(): bool
    {
        $this->info('🧪 Testing Direct API Call...');
        $this->line(str_repeat('-', 50));

        $apiKey = config('services.voyage.api_key');
        $baseUrl = config('services.voyage.base_url');

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

                $this->info('✅ API call successful!');
                $this->line('  Model: ' . ($data['model'] ?? 'unknown'));
                $this->line('  Embedding dimensions: ' . (is_array($embedding) ? count($embedding) : 'unknown'));
                $this->line('  Usage tokens: ' . ($data['usage']['total_tokens'] ?? 'unknown'));

                return true;
            } else {
                $this->error('❌ API call failed!');
                $this->line('  Status: ' . $response->status());
                $this->line('  Response: ' . $response->body());
                return false;
            }

        } catch (\Exception $e) {
            $this->error('❌ Exception during API call: ' . $e->getMessage());
            return false;
        }
    }

    private function testEmbeddingService(): bool
    {
        $this->info('🔧 Testing EmbeddingService Class...');
        $this->line(str_repeat('-', 50));

        try {
            $embeddingService = new EmbeddingService();

            $embedding = $embeddingService->generateEmbedding('This is a test bill about environmental protection.');

            if ($embedding) {
                $this->info('✅ EmbeddingService working!');
                $this->line('  Embedding dimensions: ' . count($embedding));
                $this->line('  First few values: ' . implode(', ', array_slice($embedding, 0, 5)) . '...');
                return true;
            } else {
                $this->error('❌ EmbeddingService returned null');
                return false;
            }

        } catch (\Exception $e) {
            $this->error('❌ EmbeddingService exception: ' . $e->getMessage());
            return false;
        }
    }

    private function testWithRealBill()
    {
        $this->info('📋 Testing with Real Bill Data...');
        $this->line(str_repeat('-', 50));

        // Get a bill from the database
        $bill = \App\Models\Bill::whereNotNull('bill_text')
            ->where('congress', 119)
            ->first();

        if (!$bill) {
            $this->warn('⚠️  No bills with text found in database');
            return;
        }

        $this->line("Testing with bill: {$bill->congress_id}");
        $this->line("Title: " . substr($bill->title, 0, 60) . '...');

        try {
            $embeddingService = new EmbeddingService();
            
            // Test with bill title
            $titleEmbedding = $embeddingService->generateEmbedding($bill->title);
            
            if ($titleEmbedding) {
                $this->info('✅ Generated embedding for bill title');
                $this->line('  Dimensions: ' . count($titleEmbedding));
            } else {
                $this->error('❌ Failed to generate embedding for bill title');
            }

            // Test with bill text (first 1000 chars)
            if ($bill->bill_text) {
                $shortText = substr($bill->bill_text, 0, 1000);
                $textEmbedding = $embeddingService->generateEmbedding($shortText);
                
                if ($textEmbedding) {
                    $this->info('✅ Generated embedding for bill text');
                    $this->line('  Dimensions: ' . count($textEmbedding));
                } else {
                    $this->error('❌ Failed to generate embedding for bill text');
                }
            }

        } catch (\Exception $e) {
            $this->error('❌ Exception testing with real bill: ' . $e->getMessage());
        }
    }
}