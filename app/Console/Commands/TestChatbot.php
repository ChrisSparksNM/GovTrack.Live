<?php

namespace App\Console\Commands;

use App\Services\CongressChatbotService;
use Illuminate\Console\Command;

class TestChatbot extends Command
{
    protected $signature = 'chatbot:test {question?}';
    protected $description = 'Test the congressional chatbot with sample questions';

    private CongressChatbotService $chatbotService;

    public function __construct(CongressChatbotService $chatbotService)
    {
        parent::__construct();
        $this->chatbotService = $chatbotService;
    }

    public function handle(): int
    {
        $question = $this->argument('question');
        
        if (!$question) {
            $this->info('Congressional AI Chatbot Test');
            $this->newLine();
            
            $sampleQuestions = [
                'How many bills are currently in Congress?',
                'What are the most popular policy areas this year?',
                'Show me the party breakdown in Congress',
                'Who are the most active bill sponsors lately?',
                'Tell me about recent healthcare bills'
            ];
            
            $question = $this->choice('Choose a sample question or type your own:', array_merge($sampleQuestions, ['Custom question']));
            
            if ($question === 'Custom question') {
                $question = $this->ask('Enter your question:');
            }
        }
        
        $this->info("Question: {$question}");
        $this->newLine();
        
        $this->info('🤖 Analyzing congressional data...');
        $response = $this->chatbotService->askQuestion($question);
        
        if ($response['success']) {
            $this->newLine();
            $this->info('📊 AI Response:');
            $this->line('─────────────────────────────────────────────────────────────');
            $this->line($response['response']);
            $this->line('─────────────────────────────────────────────────────────────');
            
            if (!empty($response['data_sources'])) {
                $this->newLine();
                $this->info('📋 Data Sources Used:');
                foreach ($response['data_sources'] as $source) {
                    $this->line("  • {$source}");
                }
            }
            
            if (!empty($response['statistics'])) {
                $this->newLine();
                $this->info('📈 Key Statistics:');
                foreach ($response['statistics'] as $key => $stats) {
                    $this->line("  {$key}: " . (is_array($stats) ? count($stats) . ' items' : $stats));
                }
            }
        } else {
            $this->error('❌ Error: ' . $response['error']);
            return 1;
        }
        
        return 0;
    }
}