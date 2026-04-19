<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;

$orderNumber = 'BL-2604-0001';
$order = Order::where('order_number', $orderNumber)->first();

if (!$order) {
    echo "Order $orderNumber not found.\n";
    exit;
}

echo "Order Found: ID={$order->id} | Status={$order->status} | Date={$order->order_date}\n";

$lines = $order->lines()->with('variant.product')->get();
foreach ($lines as $l) {
    echo "- [{$l->variant->sku}] {$l->variant->product->name}: Qty={$l->quantity} | Lot={$l->lot_number} | SN={$l->serial_numbers}\n";
}
