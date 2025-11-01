<?php

require_once 'bootstrap/app.php';

try {
    $app = require_once 'bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
} catch (Exception $e) {
    echo "Bootstrap error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

echo "Testing chatbot service directly..." . PHP_EOL;

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    echo "Service loaded successfully" . PHP_EOL;
    
    $result = $chatbotService->askQuestion('What are the most popular policy areas?');
    
    echo "Result:" . PHP_EOL;
    echo "Success: " . ($result['success'] ? 'true' : 'false') . PHP_EOL;
    
    if ($result['success']) {
        echo "Response: " . substr($result['response'], 0, 200) . "..." . PHP_EOL;
    } else {
        echo "Error: " . ($result['error'] ?? 'No error message') . PHP_EOL;
        if (isset($result['exception'])) {
            echo "Exception: " . $result['exception'] . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "Exception caught: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
}