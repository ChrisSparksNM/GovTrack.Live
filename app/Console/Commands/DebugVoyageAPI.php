<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DebugVoyageAPI extends Command
{
    protected $signature = 'debug:voyage-api';
    protected $description = 'Debug Voyage AI API responses';

    public function handle()
    {
        $apiKey = config('services.voyage.api_key');
        $baseUrl = config('services.voyage.base_url');
        
        $this->info('🔍 Debugging Voyage AI API');
        $this->info("API Key: " . substr($apiKey, 0, 10) . '...');
        $this->info("Base URL: {$baseUrl}");
        
        // Test single embedding
        $this->info("\n📊 Testing single embedding...");
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/embeddings', [
                'model' => 'voyage-large-2',
                'input' => 'This is a test document about healthcare policy.',
                'input_type' => 'document'
            ]);
            
            $this->info("Status: " . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info("✅ Single embedding successful");
                $this->info("Response keys: " . implode(', ', array_keys($data)));
                
                if (isset($data['data'])) {
                    $this->info("Data array length: " . count($data['data']));
                    if (!empty($data['data'])) {
                        $firstItem = $data['data'][0];
                        $this->info("First item keys: " . implode(', ', array_keys($firstItem)));
                        if (isset($firstItem['embedding'])) {
                            $this->info("Embedding dimensions: " . count($firstItem['embedding']));
                        }
                    }
                }
            } else {
                $this->error("❌ Single embedding failed");
                $this->error("Response: " . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
        
        // Test batch embedding
        $this->info("\n📊 Testing batch embedding...");
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/embeddings', [
                'model' => 'voyage-large-2',
                'input' => [
                    'Healthcare reform legislation',
                    'Infrastructure spending bills',
                    'Environmental protection policy'
                ],
                'input_type' => 'document'
            ]);
            
            $this->info("Status: " . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info("✅ Batch embedding successful");
                $this->info("Response keys: " . implode(', ', array_keys($data)));
                
                if (isset($data['data'])) {
                    $this->info("Data array length: " . count($data['data']));
                    foreach ($data['data'] as $i => $item) {
                        $this->info("Item {$i} keys: " . implode(', ', array_keys($item)));
                        if (isset($item['embedding'])) {
                            $this->info("  Embedding dimensions: " . count($item['embedding']));
                        }
                    }
                }
            } else {
                $this->error("❌ Batch embedding failed");
                $this->error("Response: " . $response->body());
            }
            
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
        
        return 0;
    }
}