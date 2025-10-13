<?php

namespace App\Console\Commands;

use App\Services\CongressChatbotService;
use Illuminate\Console\Command;

class TestEnhancedChatbot extends Command
{
    protected $signature = 'test:enhanced-chatbot {question?}';
    protected $description = 'Test the enhanced chatbot with intelligent query service';

    private CongressChatbotService $chatbotService;

    public function __construct(CongressChatbotService $chatbotService)
    {
        parent::__construct();
        $this->chatbotService = $chatbotService;
    }

    public function handle(): int
    {
        $question = $this->argument('question') ?? $this->askForQuestion();
        
        $this->info("Testing Enhanced Chatbot with question: {$question}");
        $this->newLine();
        
        $startTime = microtime(true);
        
        // Test with conversation context
        $context = [
            [
                'question' => 'What are the most popular policy areas?',
                'response' => 'Based on recent analysis, Defense and Healthcare are the most active policy areas...',
                'timestamp' => now()->subMinutes(5)->toISOString()
            ]
        ];
        
        $response = $this->chatbotService->askQuestion($question, $context);
        
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        
        if ($response['success']) {
            $this->displaySuccessfulResponse($response, $executionTime);
        } else {
            $this->displayErrorResponse($response, $executionTime);
        }
        
        return 0;
    }

    private function askForQuestion(): string
    {
        $suggestions = [
            'How many bills were introduced this year?',
            'Which states have the most Republican representatives?',
            'Show me recent bills about healthcare',
            'What are the trending topics in Congress?',
            'Who are the most active bill sponsors?'
        ];
        
        $this->info('Suggested questions:');
        foreach ($suggestions as $i => $suggestion) {
            $this->line(($i + 1) . ". {$suggestion}");
        }
        $this->newLine();
        
        $userInput = $this->ask('Enter your question (or choose a number from above)');
        return $userInput ? $userInput : $suggestions[0];
    }

    private function displaySuccessfulResponse(array $response, float $executionTime): void
    {
        $this->info("✅ Enhanced Analysis Complete ({$executionTime}ms)");
        $this->newLine();
        
        // Display analysis approach if available
        if (!empty($response['analysis_approach'])) {
            $this->comment('🧠 Analysis Approach:');
            $this->line($response['analysis_approach']);
            $this->newLine();
        }
        
        // Display context considerations if available
        if (!empty($response['context_considerations'])) {
            $this->comment('🔗 Context Considerations:');
            $this->line($response['context_considerations']);
            $this->newLine();
        }
        
        // Display analysis metadata
        if (!empty($response['analysis_metadata'])) {
            $this->comment('📊 Analysis Metadata:');
            $metadata = $response['analysis_metadata'];
            
            if (isset($metadata['queries_executed'])) {
                $this->line("  • Queries Executed: {$metadata['queries_executed']}");
            }
            
            if (isset($metadata['total_records'])) {
                $this->line("  • Total Records Analyzed: " . number_format($metadata['total_records']));
            }
            
            if (isset($metadata['avg_execution_time'])) {
                $this->line("  • Average Query Time: {$metadata['avg_execution_time']}ms");
            }
            
            if (isset($metadata['data_quality_score'])) {
                $this->line("  • Data Quality Score: {$metadata['data_quality_score']}/100");
            }
            
            $this->newLine();
        }
        
        // Display queries executed
        if (!empty($response['queries'])) {
            $this->comment('🔍 Queries Generated:');
            foreach ($response['queries'] as $i => $query) {
                $queryType = isset($query['query_type']) ? $query['query_type'] : 'unknown';
                $complexity = isset($query['complexity']) ? $query['complexity'] : 'unknown';
                $this->line(($i + 1) . ". {$query['name']} ({$queryType} - {$complexity})");
                $this->line("   Description: {$query['description']}");
            }
            $this->newLine();
        }
        
        // Display data sources
        if (!empty($response['data_sources'])) {
            $this->comment('📋 Data Sources:');
            foreach ($response['data_sources'] as $source) {
                $this->line("  • {$source}");
            }
            $this->newLine();
        }
        
        // Display the main response
        $this->comment('💬 AI Response:');
        $this->line($response['response']);
        
        // Display query results summary
        if (!empty($response['query_results'])) {
            $this->newLine();
            $this->comment('📈 Query Results Summary:');
            foreach ($response['query_results'] as $queryName => $result) {
                if (isset($result['error'])) {
                    $this->error("  ❌ {$queryName}: {$result['error']}");
                } else {
                    $count = isset($result['count']) ? $result['count'] : 0;
                    $time = isset($result['execution_time']) ? $result['execution_time'] : 0;
                    $quality = isset($result['data_quality']['score']) ? $result['data_quality']['score'] : 'N/A';
                    $this->line("  ✅ {$queryName}: {$count} records ({$time}ms, Quality: {$quality})");
                }
            }
        }
    }

    private function displayErrorResponse(array $response, float $executionTime): void
    {
        $this->error("❌ Enhanced Analysis Failed ({$executionTime}ms)");
        $this->newLine();
        
        $error = isset($response['error']) ? $response['error'] : 'Unknown error';
        $this->error('Error: ' . $error);
        
        // Still show any partial data if available
        if (!empty($response['data_sources'])) {
            $this->newLine();
            $this->comment('📋 Available Data Sources:');
            foreach ($response['data_sources'] as $source) {
                $this->line("  • {$source}");
            }
        }
    }
}