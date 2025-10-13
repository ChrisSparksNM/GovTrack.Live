<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Services\AnthropicService;
use Illuminate\Console\Command;

class TestBillSummary extends Command
{
    protected $signature = 'test:bill-summary {congress_id?}';
    protected $description = 'Test bill summary generation';

    public function handle()
    {
        $congressId = $this->argument('congress_id');
        
        if ($congressId) {
            $bill = Bill::where('congress_id', $congressId)->first();
        } else {
            // Get a random bill with text
            $bill = Bill::whereNotNull('bill_text')->inRandomOrder()->first();
        }
        
        if (!$bill) {
            $this->error('No bill found with text');
            return 1;
        }
        
        $this->info("Testing summary generation for: {$bill->congress_id}");
        $this->line("Title: {$bill->title}");
        $this->line("Text length: " . number_format(strlen($bill->bill_text)) . " characters");
        
        $anthropicService = app(AnthropicService::class);
        
        $this->info('Generating summary...');
        $result = $anthropicService->generateBillSummary(
            $bill->bill_text,
            $bill->title,
            $bill->congress_id
        );
        
        if ($result['success']) {
            $this->info('✅ Summary generated successfully!');
            $this->line('');
            $this->line($result['summary']);
            $this->line('');
            $this->info('Usage: ' . ($result['usage']['input_tokens'] ?? 0) . ' input tokens, ' . ($result['usage']['output_tokens'] ?? 0) . ' output tokens');
        } else {
            $this->error('❌ Summary generation failed!');
            $this->line('Error: ' . $result['error']);
        }
        
        return 0;
    }
}