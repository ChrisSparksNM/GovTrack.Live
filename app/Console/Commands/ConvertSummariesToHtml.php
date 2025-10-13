<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Services\AnthropicService;
use Illuminate\Console\Command;

class ConvertSummariesToHtml extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'summaries:convert-to-html {--limit=10 : Number of summaries to convert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert existing markdown AI summaries to HTML format';

    /**
     * Execute the console command.
     */
    public function handle(AnthropicService $anthropicService): int
    {
        $limit = (int) $this->option('limit');
        
        $bills = Bill::whereNotNull('ai_summary')
            ->whereNull('ai_summary_html')
            ->limit($limit)
            ->get();

        if ($bills->isEmpty()) {
            $this->info('No summaries found that need HTML conversion.');
            return 0;
        }

        $this->info("Converting {$bills->count()} summaries to HTML...");
        
        $progressBar = $this->output->createProgressBar($bills->count());
        $progressBar->start();

        $converted = 0;
        foreach ($bills as $bill) {
            try {
                $htmlSummary = $anthropicService->convertMarkdownToHtml($bill->ai_summary);
                
                $bill->update([
                    'ai_summary_html' => $htmlSummary
                ]);
                
                $converted++;
            } catch (\Exception $e) {
                $this->error("\nFailed to convert summary for {$bill->congress_id}: {$e->getMessage()}");
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Successfully converted {$converted} summaries to HTML format.");

        return 0;
    }
}