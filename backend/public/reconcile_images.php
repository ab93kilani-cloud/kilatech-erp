<?php
/**
 * Script de Réconciliation Automatique Maya.
 * Répare les liens brisés entre la base et le disque.
 */
header('Content-Type: text/plain');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "=== RÉPARATION AUTOMATIQUE DES IMAGES Maya ===\n\n";

// Chemin Senior détecté par l'audit. Maya.
$storagePath = realpath(__DIR__ . '/../storage/app/public/products');
if (!$storagePath) {
    $storagePath = '/home/u751859584/domains/kilatech.ma/public_html/api/storage/app/public/products';
}
$diskFiles = is_dir($storagePath) ? scandir($storagePath) : [];
$diskFiles = array_filter($diskFiles, function($f) { return $f !== '.' && $f !== '..'; });

$products = \App\Models\Product::all();
$repairedCount = 0;

foreach ($products as $p) {
    $justFile = basename($p->image_path);
    $fullPath = $storagePath . '/' . $justFile;

    if (!file_exists($fullPath)) {
        echo "Produit ID {$p->id} ({$p->name}) : Chemin brisé.\n";
        
        // Tentative de réconciliation par le nom (slug)
        $slug = \Illuminate\Support\Str::slug($p->name);
        $match = null;
        
        foreach ($diskFiles as $file) {
            if (str_contains(strtolower($file), $slug)) {
                $match = $file;
                break;
            }
        }
        
        if ($match) {
            $oldPath = $p->image_path;
            $newPath = 'products/' . $match;
            $p->update(['image_path' => $newPath]);
            echo " ✅ FIX : '{$oldPath}' remplacé par '{$newPath}'\n";
            $repairedCount++;
        } else {
            echo " ❌ ÉCHEC : Aucun fichier correspondant trouvé pour le slug '{$slug}'.\n";
        }
    } else {
        echo "Produit ID {$p->id} ({$p->name}) : ✅ Déjà OK.\n";
    }
}

echo "\nBILAN : {$repairedCount} produits réparés. Maya.\n";
