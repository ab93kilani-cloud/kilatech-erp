<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\PoLine;
use Illuminate\Support\Facades\DB;

$res = [
    'invoices_count' => Invoice::count(),
    'invoices_total' => Invoice::sum('total_amount'),
    'pos_count' => PurchaseOrder::count(),
    'po_lines_sum' => DB::table('po_lines')->sum(DB::raw('unit_price * ordered_quantity')),
];

file_put_contents('/tmp/counts.json', json_encode($res, JSON_PRETTY_PRINT));
echo "DONE\n";
