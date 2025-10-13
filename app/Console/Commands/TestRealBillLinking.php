<?php

namespace App\Console\Commands;

use App\Http\Controllers\ChatbotController;
use App\Models\Bill;
use Illuminate\Console\Command;
use ReflectionClass;

class TestRealBillLinking extends Command
{
    protected $signature = 'test:real-bill-linking';
    protected $description = 'Test bill linking with real bills from the database';

    public function handle(): int
    {
        // Get some real bills from the database
        $realBills = Bill::limit(3)->get();
        
        if ($realBills->isEmpty()) {
            $this->error("No bills found in database");
            return 1;
        }
        
        $this->info("🔗 Testing Bill Linking with Real Bills");
        $this->newLine();
        
        // Create test responses with real bill references
        $testResponses = [];
        
        foreach ($realBills as $bill) {
            $testResponses[] = "Here's an important bill: **{$bill->type} {$bill->number}: {$bill->title}**";
            $testResponses[] = "The bill {$bill->type} {$bill->number} is significant.";
        }
        
        $controller = new ChatbotController(app(\App\Services\CongressChatbotService::class));
        
        // Use reflection to access the private method
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('addBillLinks');
        $method->setAccessible(true);
        
        foreach ($testResponses as $i => $response) {
            $this->info("Test " . ($i + 1) . ":");
            $this->line("Input: " . $response);
            
            $linkedResponse = $method->invoke($controller, $response);
            $this->line("Output: " . $linkedResponse);
            
            // Check if link was created
            if (str_contains($linkedResponse, '<a href=')) {
                $this->info("✅ Link created successfully");
            } else {
                $this->warn("⚠️  No link created");
            }
            
            $this->newLine();
        }
        
        // Show the real bills we tested with
        $this->comment("Real bills used in test:");
        foreach ($realBills as $bill) {
            $this->line("• {$bill->congress_id} ({$bill->type} {$bill->number}): " . substr($bill->title, 0, 60) . "...");
        }
        
        return 0;
    }
}