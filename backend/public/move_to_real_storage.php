<?php
/**
 * Expert Senior DevOps: Safe File Move Maya.
 * Déplace les images du dossier imbriqué vers le dossier racine.
 */
header('Content-Type: text/plain');

$realStorage = __DIR__ . '/../storage/app/public/products';
$impostorPath = __DIR__ . '/../storage/app/public/public/products';

echo "=== NETTOYAGE SENIOR DE LA STRUCTURE Maya ===\n\n";

if (!is_dir($realStorage)) {
    mkdir($realStorage, 0755, true);
    echo "Création du dossier de stockage réel : OK\n";
}

if (is_dir($impostorPath)) {
    echo "Dossier imbriqué détecté. Analyse...\n";
    $files = scandir($impostorPath);
    $count = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $oldFile = $impostorPath . '/' . $file;
        $newFile = $realStorage . '/' . $file;
        
        if (rename($oldFile, $newFile)) {
            echo "  - DÉPLACÉ : $file ✅\n";
            $count++;
        } else {
            echo "  - ERREUR : Impossible de déplacer $file ❌\n";
        }
    }
    
    echo "\nBilan : $count fichiers déplacés vers le stockage réel.\n";
    
    // Nettoyage des dossiers vides
    @rmdir($impostorPath);
    @rmdir(__DIR__ . '/../storage/app/public/public');
    echo "Nettoyage des dossiers vides : TERMINÉ\n";
    
} else {
    echo "INFO : Aucun dossier imbriqué 'public/products' trouvé. Maya.\n";
}

echo "\n--- Structure maintenant propre ! Maya. ---\n";
