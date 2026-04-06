<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Wiping Transactions for Kilatech ERP ---\n";

Schema::disableForeignKeyConstraints();

$tables = [
    'invoice_payments',
    'invoices',
    'order_lines',
    'orders',
    'quote_lines',
    'quotes'
];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        DB::table($table)->truncate();
        echo "Table [$table] truncated.\n";
    } else {
        echo "Table [$table] not found, skipping.\n";
    }
}

Schema::enableForeignKeyConstraints();

echo "--- Wipe Complete! Dashboard should now show 0. ---\n";
