<?php
/**
 * Expert Full-Stack: Create & Sync Public Mirror Maya.
 * Crée le dossier miroir et copie les images existantes.
 */
header('Content-Type: text/plain');

$privateStorage = __DIR__ . '/../storage/app/public/products';
$publicMirror = __DIR__ . '/storage/products';

echo "=== CRÉATION DU MIROIR PUBLIC Maya ===\n\n";

if (!is_dir($publicMirror)) {
    mkdir($publicMirror, 0755, true);
    echo "Dossier miroir créé : $publicMirror ✅\n";
} else {
    echo "Dossier miroir déjà existant. ✅\n";
}

if (is_dir($privateStorage)) {
    $files = scandir($privateStorage);
    $count = 0;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $src = $privateStorage . '/' . $file;
        $dest = $publicMirror . '/' . $file;
        
        if (copy($src, $dest)) {
            echo "  - COPIÉ : $file ✅\n";
            $count++;
        }
    }
    echo "\nBilan : $count fichiers synchronisés vers le miroir public.\n";
}

echo "\n--- Le miroir est prêt ! Maya. ---\n";
echo "Test direct : https://kilatech.ma/api/storage/products/aspirateur-mobile-1772898250.jpg\n";
