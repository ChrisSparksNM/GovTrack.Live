<?php

namespace App\Console\Commands;

use App\Services\DatabaseQueryService;
use Illuminate\Console\Command;

class TestDatabaseQuery extends Command
{
    protected $signature = 'chatbot:test-sql {question}';
    protected $description = 'Test the AI-powered SQL query generation';

    private DatabaseQueryService $databaseQueryService;

    public function __construct(DatabaseQueryService $databaseQueryService)
    {
        parent::__construct();
        $this->databaseQueryService = $databaseQueryService;
    }

    public function handle(): int
    {
        $question = $this->argument('question');
        
        $this->info("Testing AI-powered SQL generation for: {$question}");
        $this->newLine();
        
        $result = $this->databaseQueryService->queryDatabase($question);
        
        if ($result['success']) {
            $this->info('✅ Queries Generated and Executed Successfully');
            $this->newLine();
            
            // Show generated queries
            $this->info('📝 Generated SQL Queries:');
            $this->line('─────────────────────────────────────────');
            foreach ($result['queries'] as $i => $query) {
                $this->line(($i + 1) . ". {$query['name']}");
                $this->line("   Description: {$query['description']}");
                $this->line("   SQL: {$query['sql']}");
                $this->newLine();
            }
            
            // Show query results summary
            $this->info('📊 Query Results Summary:');
            $this->line('─────────────────────────────────────────');
            foreach ($result['results'] as $name => $queryResult) {
                if (isset($queryResult['error'])) {
                    $this->error("❌ {$name}: {$queryResult['error']}");
                } else {
                    $this->line("✅ {$name}: {$queryResult['count']} records");
                }
            }
            $this->newLine();
            
            // Show AI analysis
            $this->info('🤖 AI Analysis:');
            $this->line('─────────────────────────────────────────');
            $this->line($result['analysis']);
            
        } else {
            $this->error('❌ Failed to process question: ' . $result['error']);
            return 1;
        }
        
        return 0;
    }
}