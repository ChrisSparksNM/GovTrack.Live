<?php

namespace App\Console\Commands;

use App\Services\CongressChatbotService;
use Illuminate\Console\Command;
use ReflectionClass;

class DebugChatbot extends Command
{
    protected $signature = 'chatbot:debug {question}';
    protected $description = 'Debug chatbot question detection';

    private CongressChatbotService $chatbotService;

    public function __construct(CongressChatbotService $chatbotService)
    {
        parent::__construct();
        $this->chatbotService = $chatbotService;
    }

    public function handle(): int
    {
        $question = $this->argument('question');
        $this->info("Debugging question: {$question}");
        $this->newLine();
        
        // Use reflection to access private methods
        $reflection = new ReflectionClass($this->chatbotService);
        
        // Test each detection method
        $methods = [
            'isAboutSpecificBill',
            'isAboutMember', 
            'isAboutParty',
            'isAboutState',
            'isAboutTrends',
            'isAboutStatistics',
            'isAboutTopic'
        ];
        
        foreach ($methods as $methodName) {
            try {
                $method = $reflection->getMethod($methodName);
                $method->setAccessible(true);
                $result = $method->invoke($this->chatbotService, strtolower($question));
                $this->line("{$methodName}: " . ($result ? 'TRUE' : 'FALSE'));
            } catch (\Exception $e) {
                $this->error("{$methodName}: ERROR - " . $e->getMessage());
            }
        }
        
        $this->newLine();
        
        // Test data gathering
        try {
            $gatherMethod = $reflection->getMethod('gatherRelevantData');
            $gatherMethod->setAccessible(true);
            $data = $gatherMethod->invoke($this->chatbotService, $question);
            
            $this->info('Data gathered:');
            $this->line('Bills: ' . count($data['bills']));
            $this->line('Members: ' . count($data['members']));
            $this->line('Statistics keys: ' . implode(', ', array_keys($data['statistics'])));
            $this->line('Sources: ' . implode(', ', $data['sources']));
        } catch (\Exception $e) {
            $this->error('Data gathering error: ' . $e->getMessage());
        }
        
        return 0;
    }
}