<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: application/json');

$tables = [
    'invoice_payments',
    'invoices',
    'order_lines',
    'orders',
    'quote_lines',
    'quotes'
];

$results = [];
foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        $results[$table] = DB::table($table)->count();
    } else {
        $results[$table] = "NOT FOUND";
    }
}

echo json_encode([
    'database' => config('database.connections.mysql.database'),
    'counts' => $results
]);
