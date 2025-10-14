<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Removing 'Presidential Actions' executive order..." . PHP_EOL;

$deleted = DB::table('executive_orders')->where('title', 'Presidential Actions')->delete();
echo "Deleted {$deleted} record(s)" . PHP_EOL;