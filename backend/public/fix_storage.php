<?php
/**
 * Script de réparation DevOps : Création du lien symbolique Maya.
 */
header('Content-Type: text/plain');

$target = __DIR__ . '/../storage/app/public';
$link = __DIR__ . '/storage';

echo "--- Diagnostic de Stockage Maya ---\n";
echo "Dossier cible (storage) : " . (is_dir($target) ? "OK" : "INTROUVABLE") . "\n";
echo "Lien actuel (public/storage) : " . (file_exists($link) ? "EXISTANT" : "ABSENT") . "\n";

if (file_exists($link)) {
    if (is_link($link)) {
        echo "Le lien existe déjà et pointe vers : " . readlink($link) . "\n";
    } else {
        echo "Un DOSSIER réel bloque le lien. Tentative de suppression...\n";
        // On ne supprime pas si c'est un dossier plein, par sécurité.
        rmdir($link);
    }
}

echo "\n--- Tentative de création du lien ---\n";
if (symlink($target, $link)) {
    echo "SUCCÈS : Le lien symbolique a été créé parfaitement. Maya.\n";
} else {
    echo "ÉCHEC : La fonction 'symlink' est bloquée sur votre serveur Hostinger. Maya.\n";
    echo "Solution alternative : Nous devrons utiliser le proxy Maya déjà en place ou copier les fichiers.\n";
}
