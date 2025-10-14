<?php

echo "=== Check Web Memory Configuration ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "1. Current PHP configuration:" . PHP_EOL;
echo "Memory limit: " . ini_get('memory_limit') . PHP_EOL;
echo "Max execution time: " . ini_get('max_execution_time') . PHP_EOL;
echo "Max input time: " . ini_get('max_input_time') . PHP_EOL;
echo "Post max size: " . ini_get('post_max_size') . PHP_EOL;
echo "Upload max filesize: " . ini_get('upload_max_filesize') . PHP_EOL;

echo "" . PHP_EOL;
echo "2. Memory usage:" . PHP_EOL;
echo "Current usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
echo "Peak usage: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;

echo "" . PHP_EOL;
echo "3. Testing simple chatbot call with memory monitoring..." . PHP_EOL;

try {
    $startMemory = memory_get_usage(true);
    echo "Starting memory: " . round($startMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    $chatbotService = app('App\Services\CongressChatbotService');
    $serviceMemory = memory_get_usage(true);
    echo "After loading service: " . round($serviceMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    // Try a very simple question first
    $result = $chatbotService->askQuestion('Hello');
    $afterMemory = memory_get_usage(true);
    echo "After simple question: " . round($afterMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
    
    if ($result['success']) {
        echo "✅ Simple question works" . PHP_EOL;
        
        // Now try the problematic question
        echo "Trying complex question..." . PHP_EOL;
        $complexResult = $chatbotService->askQuestion('What are the most popular policy areas this year?');
        $complexMemory = memory_get_usage(true);
        echo "After complex question: " . round($complexMemory / 1024 / 1024, 2) . " MB" . PHP_EOL;
        echo "Peak memory: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
        
        if ($complexResult['success']) {
            echo "✅ Complex question works too!" . PHP_EOL;
        } else {
            echo "❌ Complex question failed: " . ($complexResult['error'] ?? 'Unknown') . PHP_EOL;
        }
    } else {
        echo "❌ Even simple question failed: " . ($result['error'] ?? 'Unknown') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "💥 Exception: " . $e->getMessage() . PHP_EOL;
    echo "Memory at error: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
} catch (Error $e) {
    echo "💥 Fatal Error: " . $e->getMessage() . PHP_EOL;
    echo "Memory at error: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB" . PHP_EOL;
}

echo "" . PHP_EOL;
echo "4. Checking PHP-FPM configuration..." . PHP_EOL;

// Check if we can find PHP-FPM config
$phpFpmPaths = [
    '/etc/php/8.4/fpm/pool.d/www.conf',
    '/etc/php/8.3/fpm/pool.d/www.conf',
    '/etc/php/8.2/fpm/pool.d/www.conf',
    '/opt/php/8.4/etc/php-fpm.d/www.conf'
];

foreach ($phpFpmPaths as $path) {
    if (file_exists($path)) {
        echo "Found PHP-FPM config: $path" . PHP_EOL;
        
        // Look for memory-related settings
        $config = file_get_contents($path);
        if (strpos($config, 'php_admin_value[memory_limit]') !== false) {
            $lines = explode("\n", $config);
            foreach ($lines as $line) {
                if (strpos($line, 'memory_limit') !== false && strpos($line, ';') !== 0) {
                    echo "  $line" . PHP_EOL;
                }
            }
        }
        break;
    }
}

echo "" . PHP_EOL;
echo "=== Check Complete ===" . PHP_EOL;