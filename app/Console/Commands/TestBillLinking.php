<?php

namespace App\Console\Commands;

use App\Http\Controllers\ChatbotController;
use Illuminate\Console\Command;
use ReflectionClass;

class TestBillLinking extends Command
{
    protected $signature = 'test:bill-linking';
    protected $description = 'Test the bill linking functionality';

    public function handle(): int
    {
        $controller = new ChatbotController(app(\App\Services\CongressChatbotService::class));
        
        // Use reflection to access the private method
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('addBillLinks');
        $method->setAccessible(true);
        
        $testResponses = [
            "Here are some bills: **HR 1234: Test Healthcare Bill** and **S 567: Defense Authorization Act**",
            "The bill HR 1 is important, and so is S 2.",
            "Recent legislation includes **H.R. 100: Climate Action Bill** and **S.R. 200: Education Reform**",
            "No bills mentioned in this response."
        ];
        
        $this->info("🔗 Testing Bill Linking Functionality");
        $this->newLine();
        
        foreach ($testResponses as $i => $response) {
            $this->info("Test " . ($i + 1) . ":");
            $this->line("Input: " . $response);
            
            $linkedResponse = $method->invoke($controller, $response);
            $this->line("Output: " . $linkedResponse);
            $this->newLine();
        }
        
        return 0;
    }
}