<?php

echo "=== Fix Web Chatbot Issue ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "1. Testing if the issue is route caching..." . PHP_EOL;

// Clear route cache
exec('php artisan route:clear', $output1, $code1);
exec('php artisan config:clear', $output2, $code2);
exec('php artisan cache:clear', $output3, $code3);

echo "Caches cleared (routes: $code1, config: $code2, cache: $code3)" . PHP_EOL;

echo "" . PHP_EOL;
echo "2. Testing web controller directly with error handling..." . PHP_EOL;

try {
    // Test the exact same way the web request would work
    $request = new \Illuminate\Http\Request();
    $request->merge([
        'message' => 'What are the most popular policy areas this year?',
        'conversation_id' => 'test_' . uniqid()
    ]);
    
    // Set up session for the request
    $session = new \Illuminate\Session\Store(
        'test_session',
        new \Illuminate\Session\ArraySessionHandler(),
        uniqid()
    );
    $request->setLaravelSession($session);
    
    echo "Request setup complete" . PHP_EOL;
    
    // Create controller
    $chatbotService = app('App\Services\CongressChatbotService');
    $controller = new \App\Http\Controllers\ChatbotController($chatbotService);
    
    echo "Controller created" . PHP_EOL;
    echo "Memory before request: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    // Call the chat method
    $response = $controller->chat($request);
    
    echo "Request completed successfully!" . PHP_EOL;
    echo "Memory after request: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    echo "Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    // Check response
    $responseData = json_decode($response->getContent(), true);
    
    if ($responseData && $responseData['success']) {
        echo "✅ Web controller works!" . PHP_EOL;
        echo "Response length: " . strlen($responseData['response']) . PHP_EOL;
        
        // The issue might be in the route or middleware
        echo "" . PHP_EOL;
        echo "The controller works, so the issue is likely:" . PHP_EOL;
        echo "1. Route configuration" . PHP_EOL;
        echo "2. Middleware (CSRF, throttling)" . PHP_EOL;
        echo "3. Web server timeout" . PHP_EOL;
        
    } else {
        echo "❌ Controller failed: " . ($responseData['error'] ?? 'Unknown error') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "💥 Controller exception: " . $e->getMessage() . PHP_EOL;
    echo "File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo "Memory at error: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "3. Checking route registration..." . PHP_EOL;

// Check if chatbot routes are registered
$routes = app('router')->getRoutes();
$chatbotRoutes = [];

foreach ($routes as $route) {
    $uri = $route->uri();
    if (strpos($uri, 'chatbot') !== false) {
        $chatbotRoutes[] = [
            'method' => implode('|', $route->methods()),
            'uri' => $uri,
            'action' => $route->getActionName()
        ];
    }
}

if (!empty($chatbotRoutes)) {
    echo "Found chatbot routes:" . PHP_EOL;
    foreach ($chatbotRoutes as $route) {
        echo "  {$route['method']} /{$route['uri']} -> {$route['action']}" . PHP_EOL;
    }
} else {
    echo "❌ No chatbot routes found!" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Fix Complete ===" . PHP_EOL;