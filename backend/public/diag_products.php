<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: application/json');

try {
    $columns = Illuminate\Support\Facades\Schema::getColumnListing('products');
    $product = \App\Models\Product::find(4);
    
    echo json_encode([
        'columns' => $columns,
        'product_id_4_raw' => $product ? $product->getAttributes() : null,
        'product_id_4_json' => $product ? $product->toArray() : null,
        'app_url' => config('app.url'),
    ], JSON_PRETTY_PRINT);

} catch (\Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
