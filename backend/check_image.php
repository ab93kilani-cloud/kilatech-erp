<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$product = App\Models\Product::find(4);
if ($product) {
    echo "IMAGE_PATH: " . ($product->image_path ?? 'NULL') . "\n";
    echo "FULL_URL: " . $product->full_image_url . "\n";
} else {
    echo "Product 4 not found.";
}
