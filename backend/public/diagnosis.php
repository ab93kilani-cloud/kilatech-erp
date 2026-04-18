<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain');

echo "--- KILATECH DATABASE DIAGNOSIS ---\n\n";

try {
    $columns = Illuminate\Support\Facades\Schema::getColumnListing('invoices');
    echo "Columns in 'invoices' table: " . implode(', ', $columns) . "\n";
    
    if (in_array('due_date', $columns)) {
        echo "✅ SUCCESS: 'due_date' column EXISTS in 'invoices' table.\n";
        $count = \App\Models\Invoice::whereNull('due_date')->count();
        echo "Found $count invoices with NULL due_date.\n";
    } else {
        echo "❌ ERROR: 'due_date' column is MISSING from 'invoices' table.\n";
    }

    $migrations = \Illuminate\Support\Facades\DB::table('migrations')->orderBy('id', 'desc')->limit(5)->get();
    echo "\nLast 5 migrations:\n";
    foreach ($migrations as $m) {
        echo "- {$m->migration} (Batch: {$m->batch})\n";
    }

} catch (\Exception $e) {
    echo "❌ CRITICAL ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- SYSTEM CHECK ---\n";
echo "PHP Version: " . phpversion() . "\n";
echo "App Env: " . config('app.env') . "\n";
