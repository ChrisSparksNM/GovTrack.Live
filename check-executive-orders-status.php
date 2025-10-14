<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Executive Orders Status ===" . PHP_EOL;

$count = DB::table('executive_orders')->count();
echo "Total Executive Orders: {$count}" . PHP_EOL;

if ($count > 0) {
    $recent = DB::table('executive_orders')->orderBy('created_at', 'desc')->first();
    echo "Most recent: {$recent->title}" . PHP_EOL;
    echo "Content length: " . strlen($recent->content) . " characters" . PHP_EOL;
    echo "Has formatting: " . (strpos($recent->content, "\n") !== false ? "Yes" : "No") . PHP_EOL;
    echo "" . PHP_EOL;
    echo "First 300 characters of content:" . PHP_EOL;
    echo substr($recent->content, 0, 300) . "..." . PHP_EOL;
}