<?php

namespace App\Console\Commands;

use App\Services\AI\IntelligentQueryService;
use Illuminate\Console\Command;

class TestFastMode extends Command
{
    protected $signature = 'test:fast-mode {question?}';
    protected $description = 'Test the fast mode query generation';

    private IntelligentQueryService $intelligentQueryService;

    public function __construct(IntelligentQueryService $intelligentQueryService)
    {
        parent::__construct();
        $this->intelligentQueryService = $intelligentQueryService;
    }

    public function handle(): int
    {
        $questions = [
            $this->argument('question') ?? 'What are the trending topics in Congress?',
            'Which states have the most Republican representatives?',
            'How many bills were introduced this year?',
            'Show me Democratic representatives by state',
            'Random question that should use default'
        ];

        foreach ($questions as $question) {
            $this->info("🚀 Testing Fast Mode: {$question}");
            
            $startTime = microtime(true);
            $result = $this->intelligentQueryService->generateContextualQuery($question, [], true);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($result['success']) {
                $this->info("✅ Success ({$executionTime}ms)");
                $this->line("Queries: " . count($result['queries']));
                foreach ($result['queries'] as $query) {
                    $this->line("  • {$query['name']}: {$query['description']}");
                }
                $this->line("Approach: " . $result['analysis_approach']);
            } else {
                $this->error("❌ Failed ({$executionTime}ms): " . $result['error']);
            }
            
            $this->newLine();
        }

        return 0;
    }
}