<?php
/**
 * Script de vérification de fichiers Maya.
 */
header('Content-Type: text/plain');
$productPath = __DIR__ . '/../storage/app/public/products';
if (is_dir($productPath)) {
    echo "Fichiers dans products :\n";
    $files = scandir($productPath);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo " - $file\n";
        }
    }
} else {
    echo "Dossier introuvable Maya.";
}
