<?php

echo "=== Test Jefferson Van Drew Direct Query ===" . PHP_EOL;

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test the database query service directly with the exact question
try {
    $databaseService = app('App\Services\DatabaseQueryService');
    
    echo "Testing DatabaseQueryService with Jefferson Van Drew question..." . PHP_EOL;
    $result = $databaseService->queryDatabase("Tell me about Jefferson Van Drew's most recent bills");
    
    if ($result['success']) {
        echo "✅ Database query successful" . PHP_EOL;
        echo "Analysis: " . PHP_EOL;
        echo $result['analysis'] . PHP_EOL;
        echo "" . PHP_EOL;
        
        // Show query details
        if (isset($result['queries'])) {
            echo "Queries generated:" . PHP_EOL;
            foreach ($result['queries'] as $i => $query) {
                echo "  " . ($i + 1) . ". " . $query['description'] . PHP_EOL;
            }
        }
        
        if (isset($result['results'])) {
            echo "Results:" . PHP_EOL;
            foreach ($result['results'] as $name => $queryResult) {
                if (isset($queryResult['error'])) {
                    echo "  ❌ $name: " . $queryResult['error'] . PHP_EOL;
                } else {
                    echo "  ✅ $name: " . ($queryResult['count'] ?? 0) . " records" . PHP_EOL;
                    
                    // Show sample data if available
                    if (!empty($queryResult['data']) && $queryResult['count'] > 0) {
                        echo "    Sample records:" . PHP_EOL;
                        foreach (array_slice($queryResult['data'], 0, 3) as $record) {
                            if (isset($record->congress_id) && isset($record->title)) {
                                echo "      - {$record->congress_id}: " . substr($record->title, 0, 60) . "..." . PHP_EOL;
                            }
                        }
                    }
                }
            }
        }
        
    } else {
        echo "❌ Database query failed: " . ($result['error'] ?? 'Unknown error') . PHP_EOL;
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . PHP_EOL;
}

echo "" . PHP_EOL;
echo "=== Test Complete ===" . PHP_EOL;