<?php
// backend/public/debug_db.php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

header('Content-Type: application/json');

try {
    $results = [];
    
    // 1. Check Tables Columns
    $results['quotes_columns'] = Schema::getColumnListing('quotes');
    $results['orders_columns'] = Schema::getColumnListing('orders');
    
    // 2. Sample Data (Non-deleted)
    $results['active_quotes_count'] = DB::table('quotes')->whereNull('deleted_at')->count();
    $results['archived_quotes_count'] = DB::table('quotes')->whereNotNull('deleted_at')->count();
    
    $results['active_orders_count'] = DB::table('orders')->whereNull('deleted_at')->count();
    $results['archived_orders_count'] = DB::table('orders')->whereNotNull('deleted_at')->count();
    
    // 3. Check for any record that SHOULD be deleted but isn't
    // Quotes with status 'confirmed' that are NOT deleted
    $results['confirmed_quotes_not_deleted'] = DB::table('quotes')
        ->where('status', 'confirmed')
        ->whereNull('deleted_at')
        ->select('id', 'quote_number', 'status')
        ->get();
        
    // Orders with invoice that are NOT deleted
    $results['invoiced_orders_not_deleted'] = DB::table('orders')
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                  ->from('invoices')
                  ->whereColumn('invoices.order_id', 'orders.id');
        })
        ->whereNull('deleted_at')
        ->select('id', 'order_number', 'status')
        ->get();

    echo json_encode($results, JSON_PRETTY_PRINT);
} catch (\Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
