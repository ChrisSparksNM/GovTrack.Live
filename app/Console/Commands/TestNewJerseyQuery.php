<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DatabaseQueryService;

class TestNewJerseyQuery extends Command
{
    protected $signature = 'test:nj-query';
    protected $description = 'Test New Jersey bill queries';

    public function handle(DatabaseQueryService $queryService)
    {
        $this->info('Testing New Jersey bill queries...');
        
        $questions = [
            "What bills come out of New Jersey?",
            "Show me bills from NJ representatives",
            "What legislation has New Jersey sponsored recently?",
            "Bills sponsored by New Jersey members"
        ];
        
        foreach ($questions as $question) {
            $this->info("\n" . str_repeat('=', 60));
            $this->info("QUESTION: {$question}");
            $this->info(str_repeat('=', 60));
            
            $result = $queryService->queryDatabase($question);
            
            if ($result['success']) {
                $this->info("✅ Query successful");
                
                // Show queries generated
                $this->info("\nQueries generated:");
                foreach ($result['queries'] as $query) {
                    $this->info("- {$query['description']}");
                    $this->line("  SQL: " . substr($query['sql'], 0, 100) . "...");
                }
                
                // Show results summary
                $this->info("\nResults:");
                foreach ($result['results'] as $name => $queryResult) {
                    if (isset($queryResult['error'])) {
                        $this->error("❌ {$name}: {$queryResult['error']}");
                    } else {
                        $this->info("✅ {$name}: {$queryResult['count']} records");
                        
                        // Show sample data
                        if ($queryResult['count'] > 0) {
                            $sample = array_slice($queryResult['data'], 0, 3);
                            foreach ($sample as $row) {
                                $row = (array) $row;
                                $display = [];
                                if (isset($row['congress_id'])) $display[] = $row['congress_id'];
                                if (isset($row['title'])) $display[] = substr($row['title'], 0, 60) . '...';
                                if (isset($row['sponsor_name'])) $display[] = "Sponsor: {$row['sponsor_name']}";
                                if (isset($row['state'])) $display[] = "State: {$row['state']}";
                                
                                $this->line("  • " . implode(' | ', $display));
                            }
                        }
                    }
                }
                
                // Show AI analysis
                if (!empty($result['analysis'])) {
                    $this->info("\nAI Analysis:");
                    $this->line($result['analysis']);
                }
                
            } else {
                $this->error("❌ Query failed: {$result['error']}");
            }
            
            $this->info("\n");
        }
        
        return 0;
    }
}