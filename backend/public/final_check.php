<?php
/**
 * Diagnostic Final : État du catalogue Maya.
 */
header('Content-Type: text/plain');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "--- État du Catalogue Maya ---\n\n";

$products = \App\Models\Product::orderBy('id', 'desc')->take(5)->get();
$storagePath = __DIR__ . '/../storage/app/public/products';

foreach ($products as $p) {
    $justFile = basename($p->image_path);
    $fullPath = $storagePath . '/' . $justFile;
    $exists = file_exists($fullPath) ? "✅ OUI" : "❌ NON";
    
    echo "ID: {$p->id} | Nom: {$p->name} | Image en DB: {$p->image_path} | Fichier sur Disque: $exists\n";
}

echo "\n--- Liste des 5 derniers fichiers sur disque ---\n";
$files = is_dir($storagePath) ? scandir($storagePath) : [];
$files = array_filter($files, function($f) { return $f !== '.' && $f !== '..'; });
arsort($files);
foreach (array_slice($files, 0, 5) as $f) {
    echo " - $f\n";
}
