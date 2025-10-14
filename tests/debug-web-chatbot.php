<?php

echo "=== Debug Web Chatbot Error ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    echo "1. Testing web controller directly..." . PHP_EOL;
    
    // Simulate the web request
    $request = new \Illuminate\Http\Request();
    $request->merge([
        'message' => 'What are the most popular policy areas this year?',
        'conversation_id' => 'test_' . uniqid()
    ]);
    
    echo "Request created successfully" . PHP_EOL;
    
    // Create controller instance
    $chatbotService = app('App\Services\CongressChatbotService');
    $controller = new \App\Http\Controllers\ChatbotController($chatbotService);
    
    echo "Controller created successfully" . PHP_EOL;
    echo "Memory before request: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    // Call the chat method
    $response = $controller->chat($request);
    
    echo "Request processed successfully" . PHP_EOL;
    echo "Memory after request: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    echo "Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    // Get response content
    $responseData = json_decode($response->getContent(), true);
    
    if ($responseData['success']) {
        echo "✅ SUCCESS!" . PHP_EOL;
        echo "Response length: " . strlen($responseData['response']) . PHP_EOL;
        echo "Data sources: " . count($responseData['data_sources'] ?? []) . PHP_EOL;
        echo "First 200 chars: " . substr($responseData['response'], 0, 200) . "..." . PHP_EOL;
    } else {
        echo "❌ FAILED: " . ($responseData['error'] ?? 'Unknown error') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "💥 EXCEPTION in web controller:" . PHP_EOL;
    echo "Message: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Memory at error: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    echo "" . PHP_EOL;
    echo "Stack trace:" . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
} catch (Error $e) {
    echo "💥 FATAL ERROR in web controller:" . PHP_EOL;
    echo "Message: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Memory at error: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "2. Testing chatbot service directly (like CLI)..." . PHP_EOL;

try {
    $chatbotService = app('App\Services\CongressChatbotService');
    $result = $chatbotService->askQuestion('What are the most popular policy areas this year?');
    
    if ($result['success']) {
        echo "✅ Direct service call works!" . PHP_EOL;
        echo "Method: " . ($result['method'] ?? 'unknown') . PHP_EOL;
        echo "Response length: " . strlen($result['response']) . PHP_EOL;
    } else {
        echo "❌ Direct service call failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "💥 Direct service exception: " . $e->getMessage() . PHP_EOL;
}