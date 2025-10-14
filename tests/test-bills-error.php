<?php

echo "=== Test Bills Error ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "1. Testing chatbot with bill question..." . PHP_EOL;
    
    $chatbotService = app('App\Services\CongressChatbotService');
    
    echo "Service loaded successfully" . PHP_EOL;
    
    $result = $chatbotService->askQuestion('How many bills are currently in Congress?');
    
    echo "Question processed successfully" . PHP_EOL;
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
    
} catch (Exception $e) {
    echo "💥 EXCEPTION CAUGHT:" . PHP_EOL;
    echo "Message: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
} catch (Error $e) {
    echo "💥 FATAL ERROR CAUGHT:" . PHP_EOL;
    echo "Message: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}