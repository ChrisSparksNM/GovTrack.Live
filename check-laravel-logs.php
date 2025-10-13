<?php

echo "=== Check Laravel Logs for Chatbot Errors ===" . PHP_EOL;

try {
    // Check Laravel log file
    $logPath = storage_path('logs/laravel.log');
    
    if (file_exists($logPath)) {
        echo "✅ Laravel log file exists: $logPath" . PHP_EOL;
        
        // Get the last 50 lines of the log
        $logContent = file_get_contents($logPath);
        $lines = explode("\n", $logContent);
        $recentLines = array_slice($lines, -50);
        
        echo "" . PHP_EOL;
        echo "Recent log entries (last 50 lines):" . PHP_EOL;
        echo str_repeat("-", 80) . PHP_EOL;
        
        foreach ($recentLines as $line) {
            if (!empty(trim($line))) {
                echo $line . PHP_EOL;
            }
        }
        
        echo str_repeat("-", 80) . PHP_EOL;
        
        // Look for specific error patterns
        $errorPatterns = [
            'chatbot',
            'CongressChatbotService',
            'ChatbotController',
            'memory',
            'Fatal error',
            'Exception',
            'Error:'
        ];
        
        echo "" . PHP_EOL;
        echo "Searching for chatbot-related errors..." . PHP_EOL;
        
        $foundErrors = false;
        foreach ($lines as $lineNum => $line) {
            foreach ($errorPatterns as $pattern) {
                if (stripos($line, $pattern) !== false) {
                    echo "Line " . ($lineNum + 1) . ": " . trim($line) . PHP_EOL;
                    $foundErrors = true;
                }
            }
        }
        
        if (!$foundErrors) {
            echo "No chatbot-related errors found in recent logs" . PHP_EOL;
        }
        
    } else {
        echo "❌ Laravel log file not found at: $logPath" . PHP_EOL;
        
        // Check if logs directory exists
        $logsDir = storage_path('logs');
        if (is_dir($logsDir)) {
            echo "Logs directory exists, checking for other log files..." . PHP_EOL;
            $files = scandir($logsDir);
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..') {
                    echo "  - $file" . PHP_EOL;
                }
            }
        } else {
            echo "❌ Logs directory does not exist: $logsDir" . PHP_EOL;
        }
    }
    
} catch (Exception $e) {
    echo "Error reading logs: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Check Complete ===" . PHP_EOL;