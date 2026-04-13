<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\Quote;

echo "--- DIAGNOSTIC DASHBOARD ---\n";
try {
    echo "Invoices count (with trashed): " . Invoice::withTrashed()->count() . "\n";
    echo "Quotes count: " . Quote::count() . "\n";
    
    $lastInvoice = Invoice::withTrashed()->latest()->first();
    if ($lastInvoice) {
        echo "Last Invoice Date: " . ($lastInvoice->invoice_date ?? $lastInvoice->created_at) . "\n";
        echo "Last Invoice Total: " . $lastInvoice->total_amount . "\n";
    } else {
        echo "No Invoices found.\n";
    }

    $sampleSum = Invoice::withTrashed()->sum('total_amount');
    echo "Global Sum of Invoices: " . $sampleSum . "\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
