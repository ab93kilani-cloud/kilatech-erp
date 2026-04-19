<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\StockUnit;
use App\Models\ProductVariant;
use App\Models\Lot;

$sku = 'PRD-00122-V01';
$variant = ProductVariant::where('sku', $sku)->first();

if (!$variant) {
    echo "Variant $sku not found.\n";
    exit;
}

echo "Analyzing SKU: $sku ({$variant->name_variation})\n";

$lots = Lot::where('variant_id', $variant->id)->get();
echo "Number of lots found for this variant: " . $lots->count() . "\n";

foreach ($lots as $lot) {
    $total = StockUnit::where('lot_id', $lot->id)->count();
    $sold = StockUnit::where('lot_id', $lot->id)->where('status', 'vendue')->count();
    $avail = StockUnit::where('lot_id', $lot->id)->where('status', 'disponible')->count();
    
    echo "Lot: {$lot->lot_number} | Total: $total | Sold: $sold | Avail: $avail | Exp: {$lot->expiration_date}\n";
}

$unitsWithoutLot = StockUnit::whereDoesntHave('lot')->count();
if ($unitsWithoutLot > 0) {
    echo "WARNING: There are $unitsWithoutLot units without a linked lot in the system.\n";
}
