<?php

echo "=== Add Simple Test Route ===" . PHP_EOL;

// Add a very simple test route to see if routing works at all
$simpleRouteContent = "
// Simple test route
Route::get('/test-simple', function() {
    return response()->json([
        'status' => 'working',
        'memory' => ini_get('memory_limit'),
        'time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION
    ]);
});
";

// Read current web routes
$webRoutesPath = 'routes/web.php';
$currentRoutes = file_get_contents($webRoutesPath);

// Check if test route already exists
if (strpos($currentRoutes, '/test-simple') === false) {
    // Add the test route at the end
    $newRoutes = rtrim($currentRoutes) . "\n\n" . $simpleRouteContent;
    file_put_contents($webRoutesPath, $newRoutes);
    
    echo "✅ Simple test route added" . PHP_EOL;
    echo "Test: https://govtracklive.on-forge.com/test-simple" . PHP_EOL;
} else {
    echo "Simple test route already exists" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "If the simple route works but debug-chatbot doesn't," . PHP_EOL;
echo "then the issue is specifically with the chatbot service." . PHP_EOL;