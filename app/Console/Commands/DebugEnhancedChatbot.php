<?php

namespace App\Console\Commands;

use App\Services\AI\IntelligentQueryService;
use App\Services\CongressChatbotService;
use Illuminate\Console\Command;

class DebugEnhancedChatbot extends Command
{
    protected $signature = 'debug:enhanced-chatbot';
    protected $description = 'Debug the enhanced chatbot issues';

    private IntelligentQueryService $intelligentQueryService;
    private CongressChatbotService $chatbotService;

    public function __construct(
        IntelligentQueryService $intelligentQueryService,
        CongressChatbotService $chatbotService
    ) {
        parent::__construct();
        $this->intelligentQueryService = $intelligentQueryService;
        $this->chatbotService = $chatbotService;
    }

    public function handle(): int
    {
        $question = "What are the trending topics in Congress this year?";
        
        $this->info("🔍 Debugging Enhanced Chatbot");
        $this->info("Question: {$question}");
        $this->newLine();
        
        // Test 1: Direct IntelligentQueryService
        $this->info("📋 Test 1: Direct IntelligentQueryService");
        $startTime = microtime(true);
        
        try {
            $queryResult = $this->intelligentQueryService->generateContextualQuery($question, []);
            $time1 = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($queryResult['success']) {
                $this->info("✅ Query generation successful ({$time1}ms)");
                $this->line("Queries generated: " . count($queryResult['queries']));
                
                foreach ($queryResult['queries'] as $i => $query) {
                    $this->line("  " . ($i + 1) . ". {$query['name']}: {$query['description']}");
                }
            } else {
                $this->error("❌ Query generation failed ({$time1}ms)");
                $this->error("Error: " . $queryResult['error']);
            }
        } catch (\Exception $e) {
            $time1 = round((microtime(true) - $startTime) * 1000, 2);
            $this->error("❌ Exception in query generation ({$time1}ms)");
            $this->error("Error: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Test 2: Full ChatbotService
        $this->info("📋 Test 2: Full CongressChatbotService");
        $startTime = microtime(true);
        
        try {
            $response = $this->chatbotService->askQuestion($question, []);
            $time2 = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($response['success']) {
                $this->info("✅ Full chatbot successful ({$time2}ms)");
                $this->line("Response length: " . strlen($response['response']));
                $this->line("Data sources: " . count($response['data_sources'] ?? []));
                
                if (isset($response['analysis_metadata'])) {
                    $this->line("Analysis metadata: " . json_encode($response['analysis_metadata']));
                }
            } else {
                $this->error("❌ Full chatbot failed ({$time2}ms)");
                $this->error("Error: " . $response['error']);
            }
        } catch (\Exception $e) {
            $time2 = round((microtime(true) - $startTime) * 1000, 2);
            $this->error("❌ Exception in full chatbot ({$time2}ms)");
            $this->error("Error: " . $e->getMessage());
        }
        
        $this->newLine();
        
        // Test 3: Check logs for recent errors
        $this->info("📋 Test 3: Recent Log Entries");
        $this->checkRecentLogs();
        
        return 0;
    }

    private function checkRecentLogs(): void
    {
        $logFile = storage_path('logs/laravel.log');
        
        if (!file_exists($logFile)) {
            $this->warn("Log file not found");
            return;
        }
        
        $lines = file($logFile);
        $recentLines = array_slice($lines, -20); // Last 20 lines
        
        $this->line("Recent log entries:");
        foreach ($recentLines as $line) {
            if (str_contains($line, 'ERROR') || str_contains($line, 'WARNING')) {
                $this->warn(trim($line));
            } elseif (str_contains($line, 'Enhanced query') || str_contains($line, 'IntelligentQuery')) {
                $this->line(trim($line));
            }
        }
    }
}