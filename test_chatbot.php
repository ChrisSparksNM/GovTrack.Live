<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo "Testing chatbot service...\n";
    
    $chatbot = app(App\Services\CongressChatbotService::class);
    echo "Service loaded successfully\n";
    
    $result = $chatbot->askQuestion('Tell me about bills related to China');
    
    echo "Success: " . ($result['success'] ? 'true' : 'false') . "\n";
    
    if ($result['success']) {
        echo "Response length: " . strlen($result['response']) . " characters\n";
        echo "Method used: " . ($result['method'] ?? 'fallback') . "\n";
        echo "First 200 chars: " . substr($result['response'], 0, 200) . "...\n";
    } else {
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}