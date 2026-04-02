<?php
/**
 * Audit des données en base Maya.
 */
header('Content-Type: text/plain');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "AUDIT DATABASE - IMAGES Maya.\n\n";
$products = \App\Models\Product::select('id', 'name', 'image_path')->get();
foreach ($products as $p) {
    echo "ID: {$p->id} | Name: {$p->name} | Path: '{$p->image_path}'\n";
}
