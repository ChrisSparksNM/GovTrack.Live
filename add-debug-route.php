<?php

echo "=== Add Debug Route ===" . PHP_EOL;

// This will add a temporary debug route to test the chatbot without middleware
$routeContent = "
// Temporary debug route - remove after testing
Route::get('/debug-chatbot', function() {
    try {
        \$chatbotService = app('App\\Services\\CongressChatbotService');
        \$result = \$chatbotService->askQuestion('What are the most popular policy areas this year?');
        
        return response()->json([
            'debug' => true,
            'success' => \$result['success'],
            'method' => \$result['method'] ?? 'unknown',
            'response_length' => strlen(\$result['response']),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'error' => \$result['error'] ?? null,
            'first_200_chars' => substr(\$result['response'], 0, 200)
        ]);
    } catch (Exception \$e) {
        return response()->json([
            'debug' => true,
            'success' => false,
            'exception' => \$e->getMessage(),
            'file' => \$e->getFile() . ':' . \$e->getLine(),
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB'
        ], 500);
    }
});
";

// Read current web routes
$webRoutesPath = 'routes/web.php';
$currentRoutes = file_get_contents($webRoutesPath);

// Check if debug route already exists
if (strpos($currentRoutes, '/debug-chatbot') === false) {
    // Add the debug route at the end
    $newRoutes = rtrim($currentRoutes) . "\n\n" . $routeContent;
    file_put_contents($webRoutesPath, $newRoutes);
    
    echo "✅ Debug route added to routes/web.php" . PHP_EOL;
    echo "You can now test: http://your-domain.com/debug-chatbot" . PHP_EOL;
} else {
    echo "Debug route already exists" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "After testing, you can remove the debug route from routes/web.php" . PHP_EOL;