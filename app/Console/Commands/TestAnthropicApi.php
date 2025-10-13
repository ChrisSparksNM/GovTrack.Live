<?php

namespace App\Console\Commands;

use App\Services\AnthropicService;
use Illuminate\Console\Command;

class TestAnthropicApi extends Command
{
    protected $signature = 'test:anthropic';
    protected $description = 'Test Anthropic API connection';

    public function handle()
    {
        $this->info('Testing Anthropic API connection...');
        
        $anthropicService = app(AnthropicService::class);
        
        // Test with a simple prompt
        $result = $anthropicService->generateQuickSummary(
            "This is a test bill about environmental protection. It establishes new regulations for carbon emissions and provides funding for renewable energy projects.",
            "Test Environmental Protection Act"
        );
        
        if ($result['success']) {
            $this->info('✅ API connection successful!');
            $this->line('Response: ' . $result['summary']);
        } else {
            $this->error('❌ API connection failed!');
            $this->line('Error: ' . $result['error']);
        }
        
        return 0;
    }
}