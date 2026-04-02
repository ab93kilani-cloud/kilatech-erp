<?php
/**
 * Script de secours pour créer le lien symbolique storage sur Hostinger Maya.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain');

// Nous sommes dans api/public Maya.
$target = realpath(__DIR__ . '/../storage/app/public');
$link = realpath(__DIR__ . '/../..') . '/storage';

echo "Tentative de création du lien symbolique...\n";
echo "Cible (api/storage/app/public) : $target\n";
echo "Lien (public_html/storage) : $link\n\n";

if (!$target) {
    die("ERREUR : Le dossier cible n'existe pas sur le serveur. Vérifiez que api/storage/app/public est présent.\n");
}

if (file_exists($link)) {
    if (is_link($link)) {
        echo "L'ancien lien existait déjà. Suppression...\n";
        unlink($link);
    } else {
        echo "Attention : Un dossier réel 'storage' existe déjà au lieu d'un lien. Renommage en sauvegarde...\n";
        $backup = $link . '_backup_' . time();
        if (rename($link, $backup)) {
            echo "Dossier renommé avec succès vers $backup\n";
        } else {
            die("ERREUR : Impossible de renommer le dossier existant.\n");
        }
    }
}

echo "Appel à symlink($target, $link)...\n";
if (symlink($target, $link)) {
    echo "SUCCÈS : Le lien symbolique a été créé avec succès ! Maya.\n";
    echo "Vos images devraient maintenant apparaître sur https://kilatech.ma\n";
} else {
    $err = error_get_last();
    echo "ÉCHEC : Impossible de créer le lien.\n";
    echo "Message d'erreur PHP : " . ($err['message'] ?? 'Inconnu') . "\n";
    echo "Vérifiez les permissions de votre dossier public_html (il doit être en 755).\n";
}
