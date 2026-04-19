<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockUnit;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

$variants = ProductVariant::with(['product'])->get();

echo "Stock Summary:\n";
foreach ($variants as $v) {
    $count = StockUnit::whereHas('lot', function($q) use ($v) {
        $q->where('variant_id', $v->id);
    })->where('status', 'disponible')->count();
    
    if ($count > 0) {
        echo "[{$v->sku}] " . $v->product->name . " ($v->name_variation): $count units available\n";
    }
}

echo "\nLatest Invoiced Items without stock units (Sold status):\n";
$soldCount = StockUnit::where('status', 'vendue')->count();
echo "Total units marked as sold: $soldCount\n";
