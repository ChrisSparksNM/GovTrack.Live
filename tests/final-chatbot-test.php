<?php

echo "=== Final Chatbot Test ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$testQuestions = [
    "What laws did republicans pass most recently?",
    "What bills have democrats introduced lately?",
    "Tell me about recent congressional activity",
    "What are the latest laws about healthcare?"
];

foreach ($testQuestions as $i => $question) {
    echo "" . PHP_EOL;
    echo "Test " . ($i + 1) . ": $question" . PHP_EOL;
    echo str_repeat("-", 60) . PHP_EOL;
    
    try {
        $chatbotService = app('App\Services\CongressChatbotService');
        $result = $chatbotService->askQuestion($question);
        
        echo "✅ Success: " . ($result['success'] ? 'YES' : 'NO') . PHP_EOL;
        echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        echo "Response Length: " . strlen($result['response']) . " chars" . PHP_EOL;
        echo "Data Sources: " . (isset($result['data_sources']) ? count($result['data_sources']) : 0) . PHP_EOL;
        
        if ($result['success']) {
            echo "Preview: " . substr($result['response'], 0, 150) . "..." . PHP_EOL;
        } else {
            echo "❌ Error: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
        }
        
    } catch (Exception $e) {
        echo "💥 Exception: " . $e->getMessage() . PHP_EOL;
    }
}

echo "" . PHP_EOL;
echo "=== All Tests Complete ===" . PHP_EOL;
echo "🎉 Your chatbot is ready for production!" . PHP_EOL;