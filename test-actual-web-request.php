<?php

echo "=== Test Actual Web Request ===" . PHP_EOL;

// Test the actual web endpoint like the frontend does
$url = 'http://localhost/chatbot/chat'; // Adjust if needed
$data = [
    'message' => 'What are the most popular policy areas this year?',
    'conversation_id' => 'test_' . uniqid()
];

echo "Testing actual HTTP request to: $url" . PHP_EOL;
echo "Data: " . json_encode($data) . PHP_EOL;

try {
    // Initialize cURL
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minute timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest'
    ]);
    
    // Add CSRF token if we can get it
    // This is a simplified test - in real app, you'd need proper CSRF handling
    
    echo "Sending request..." . PHP_EOL;
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    echo "HTTP Code: $httpCode" . PHP_EOL;
    
    if ($error) {
        echo "cURL Error: $error" . PHP_EOL;
    }
    
    if ($response) {
        echo "Response received (" . strlen($response) . " bytes)" . PHP_EOL;
        
        // Try to decode JSON
        $jsonResponse = json_decode($response, true);
        
        if ($jsonResponse) {
            echo "JSON Response:" . PHP_EOL;
            echo json_encode($jsonResponse, JSON_PRETTY_PRINT) . PHP_EOL;
        } else {
            echo "Raw Response (first 1000 chars):" . PHP_EOL;
            echo substr($response, 0, 1000) . PHP_EOL;
            
            // Look for error patterns in HTML response
            if (stripos($response, 'error') !== false || stripos($response, 'exception') !== false) {
                echo "" . PHP_EOL;
                echo "Error detected in response!" . PHP_EOL;
            }
        }
    } else {
        echo "No response received" . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Alternative: Check web server error logs ===" . PHP_EOL;

// Common web server error log locations
$errorLogPaths = [
    '/var/log/nginx/error.log',
    '/var/log/apache2/error.log',
    '/home/forge/.forge/nginx-error.log',
    '/home/forge/govtracklive.on-forge.com/storage/logs/laravel.log'
];

foreach ($errorLogPaths as $path) {
    if (file_exists($path)) {
        echo "Found log: $path" . PHP_EOL;
        
        // Get last few lines
        $lines = `tail -20 "$path" 2>/dev/null`;
        if ($lines) {
            echo "Recent entries:" . PHP_EOL;
            echo $lines . PHP_EOL;
        }
        break;
    }
}

echo "=== Test Complete ===" . PHP_EOL;