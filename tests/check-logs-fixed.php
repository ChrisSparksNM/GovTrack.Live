<?php

echo "=== Check Laravel Logs (Fixed) ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Check Laravel log file
    $logPath = storage_path('logs/laravel.log');
    
    if (file_exists($logPath)) {
        echo "✅ Laravel log file exists: $logPath" . PHP_EOL;
        
        // Get file size
        $fileSize = filesize($logPath);
        echo "Log file size: " . round($fileSize / 1024, 2) . " KB" . PHP_EOL;
        
        // Get the last 100 lines of the log
        $command = "tail -100 " . escapeshellarg($logPath);
        $recentLines = shell_exec($command);
        
        if ($recentLines) {
            echo "" . PHP_EOL;
            echo "Recent log entries (last 100 lines):" . PHP_EOL;
            echo str_repeat("-", 80) . PHP_EOL;
            echo $recentLines . PHP_EOL;
            echo str_repeat("-", 80) . PHP_EOL;
        } else {
            // Fallback to PHP file reading
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
    
    echo "" . PHP_EOL;
    echo "=== Also checking web server logs ===" . PHP_EOL;
    
    // Check common web server log locations
    $webLogPaths = [
        '/var/log/nginx/error.log',
        '/var/log/nginx/govtracklive.on-forge.com.error.log',
        '/home/forge/.forge/nginx-error.log'
    ];
    
    foreach ($webLogPaths as $path) {
        if (file_exists($path)) {
            echo "Found web server log: $path" . PHP_EOL;
            
            $command = "tail -20 " . escapeshellarg($path) . " 2>/dev/null";
            $webLogContent = shell_exec($command);
            
            if ($webLogContent) {
                echo "Recent web server errors:" . PHP_EOL;
                echo $webLogContent . PHP_EOL;
            }
            break;
        }
    }
    
} catch (Exception $e) {
    echo "Error reading logs: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Check Complete ===" . PHP_EOL;