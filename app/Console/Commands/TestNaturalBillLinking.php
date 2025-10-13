<?php

namespace App\Console\Commands;

use App\Http\Controllers\ChatbotController;
use App\Models\Bill;
use Illuminate\Console\Command;
use ReflectionClass;

class TestNaturalBillLinking extends Command
{
    protected $signature = 'test:natural-bill-linking';
    protected $description = 'Test bill linking with natural language references';

    public function handle(): int
    {
        // Get some real bills from the database to test with
        $senateBill = Bill::where('type', 'S')->first();
        $houseResolution = Bill::where('type', 'HRES')->first();
        $senateResolution = Bill::where('type', 'SRES')->first();
        
        $this->info("🔗 Testing Natural Language Bill Linking");
        $this->newLine();
        
        $testResponses = [
            // Natural language patterns from your example
            "Senate Resolution 444: A strongly-worded resolution condemning Chinese leader Xi Jinping",
            "Senate Bill 2960: Aims to develop economic tools to protect Taiwan Chinese aggression",
            "House Bill 5491: Focuses on helping Americans who are detained in China",
            "House Resolution 123: Test resolution about something important",
            
            // Test with real bills if available
        ];
        
        if ($senateBill) {
            $testResponses[] = "Senate Bill {$senateBill->number}: {$senateBill->title}";
        }
        
        if ($houseResolution) {
            $testResponses[] = "House Resolution {$houseResolution->number}: {$houseResolution->title}";
        }
        
        if ($senateResolution) {
            $testResponses[] = "Senate Resolution {$senateResolution->number}: {$senateResolution->title}";
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
                $this->warn("⚠️  No link created (bill may not exist in database)");
            }
            
            $this->newLine();
        }
        
        // Show available bill types for reference
        $this->comment("Available bill types in database:");
        $billTypes = Bill::select('type', \DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();
            
        foreach ($billTypes as $billType) {
            $this->line("• {$billType->type}: {$billType->count} bills");
        }
        
        return 0;
    }
}