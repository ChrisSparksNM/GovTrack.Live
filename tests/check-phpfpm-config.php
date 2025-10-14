<?php

echo "=== Check PHP-FPM Configuration ===" . PHP_EOL;

// Check PHP-FPM configuration for memory limits
$phpFpmPath = '/etc/php/8.4/fpm/pool.d/www.conf';

if (file_exists($phpFpmPath)) {
    echo "Reading PHP-FPM config: $phpFpmPath" . PHP_EOL;
    
    $config = file_get_contents($phpFpmPath);
    $lines = explode("\n", $config);
    
    echo "" . PHP_EOL;
    echo "Memory and resource related settings:" . PHP_EOL;
    echo str_repeat("-", 50) . PHP_EOL;
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        
        // Look for memory, time, and resource limits
        if (preg_match('/(memory_limit|max_execution_time|max_input_time|pm\.|request_terminate_timeout)/i', $line) && 
            !str_starts_with($line, ';')) {
            echo "Line " . ($lineNum + 1) . ": $line" . PHP_EOL;
        }
    }
    
    echo str_repeat("-", 50) . PHP_EOL;
    
    // Check for specific problematic settings
    if (strpos($config, 'php_admin_value[memory_limit]') !== false) {
        echo "" . PHP_EOL;
        echo "⚠️  Found memory_limit override in PHP-FPM config!" . PHP_EOL;
        
        preg_match_all('/php_admin_value\[memory_limit\]\s*=\s*(.+)/', $config, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $limit) {
                echo "PHP-FPM memory limit: $limit" . PHP_EOL;
            }
        }
    }
    
    // Check process manager settings
    echo "" . PHP_EOL;
    echo "Process manager settings:" . PHP_EOL;
    
    $pmSettings = ['pm ', 'pm.max_children', 'pm.start_servers', 'pm.min_spare_servers', 'pm.max_spare_servers'];
    
    foreach ($lines as $line) {
        $line = trim($line);
        foreach ($pmSettings as $setting) {
            if (str_starts_with($line, $setting) && !str_starts_with($line, ';')) {
                echo "$line" . PHP_EOL;
            }
        }
    }
    
} else {
    echo "❌ PHP-FPM config not found at: $phpFpmPath" . PHP_EOL;
    
    // Try other common locations
    $altPaths = [
        '/etc/php/8.3/fpm/pool.d/www.conf',
        '/etc/php/8.2/fpm/pool.d/www.conf',
        '/opt/php/8.4/etc/php-fpm.d/www.conf'
    ];
    
    foreach ($altPaths as $path) {
        if (file_exists($path)) {
            echo "Found alternative config: $path" . PHP_EOL;
            break;
        }
    }
}

echo "" . PHP_EOL;
echo "=== Recommendations ===" . PHP_EOL;

echo "If PHP-FPM has a lower memory limit than 1024M:" . PHP_EOL;
echo "1. Edit the PHP-FPM config file" . PHP_EOL;
echo "2. Add or update: php_admin_value[memory_limit] = 1024M" . PHP_EOL;
echo "3. Restart PHP-FPM: sudo service php8.4-fpm restart" . PHP_EOL;
echo "" . PHP_EOL;
echo "Or in Forge dashboard:" . PHP_EOL;
echo "1. Go to Server → PHP → Edit Configuration" . PHP_EOL;
echo "2. Set memory_limit = 1024M" . PHP_EOL;
echo "3. Save and restart PHP" . PHP_EOL;