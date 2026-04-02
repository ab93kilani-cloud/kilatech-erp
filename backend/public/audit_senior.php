<?php
/**
 * Audit de Réconciliation Senior Maya.
 * Compare la base de données avec le disque.
 */
header('Content-Type: text/plain');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "=== AUDIT DE RÉCONCILIATION SENIOR Maya ===\n\n";

$storagePath = __DIR__ . '/../storage/app/public/products';
$diskFiles = is_dir($storagePath) ? scandir($storagePath) : [];
$diskFiles = array_filter($diskFiles, function($f) { return $f !== '.' && $f !== '..'; });

$products = \App\Models\Product::select('id', 'name', 'image_path')->get();

echo "ANALYSE DES PRODUITS :\n";
echo str_repeat("-", 80) . "\n";
printf("%-5s | %-20s | %-30s | %-10s\n", "ID", "Nom", "Chemin en Base", "Statut");
echo str_repeat("-", 80) . "\n";

foreach ($products as $p) {
    // Nettoyage du chemin pour comparaison
    $justFile = basename($p->image_path);
    $exists = in_array($justFile, $diskFiles) ? "✅ OK" : "❌ MANQUANT";
    
    printf("%-5d | %-20s | %-30s | %-10s\n", 
        $p->id, 
        substr($p->name, 0, 20), 
        $p->image_path, 
        $exists
    );
}

echo "\nFICHIERS ORPHELINS (Sur disque mais pas en base) :\n";
echo str_repeat("-", 80) . "\n";
$dbPaths = $products->pluck('image_path')->map(function($p) { return basename($p); })->toArray();
foreach ($diskFiles as $file) {
    if (!in_array($file, $dbPaths)) {
        echo " - $file\n";
    }
}

echo "\nCONSEIL SENIOR :\n";
echo "Si un produit est 'MANQUANT' mais qu'un fichier orphelin lui ressemble,\n";
echo "il suffit de renommer l'entrée en base de données. Maya.\n";
