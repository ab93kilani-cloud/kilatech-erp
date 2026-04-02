<?php
/**
 * Script de secours pour créer le lien symbolique storage sur Hostinger Maya.
 */
header('Content-Type: text/plain');

// Nous sommes dans api/public Maya.
$target = __DIR__ . '/../storage/app/public';
$link = __DIR__ . '/../../storage';

echo "Tentative de création du lien symbolique...\n";
echo "Cible (api/storage/app/public) : $target\n";
echo "Lien (public_html/storage) : $link\n\n";

if (file_exists($link)) {
    if (is_link($link)) {
        echo "L'ancien lien existait déjà. Suppression...\n";
        unlink($link);
    } else {
        echo "Attention : Un dossier réel 'storage' existe déjà au lieu d'un lien. Renommage en sauvegarde...\n";
        rename($link, $link . '_backup_' . time());
    }
}

if (symlink($target, $link)) {
    echo "SUCCÈS : Le lien symbolique a été créé avec succès ! Maya.\n";
    echo "Vos images devraient maintenant apparaître sur https://kilatech.ma\n";
} else {
    echo "ÉCHEC : Impossible de créer le lien. Vérifiez les permissions de votre dossier public_html.\n";
}
