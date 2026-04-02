<?php
/**
 * Script de secours v3 - Tentative via shell_exec Maya.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: text/plain');

$target = realpath(__DIR__ . '/../storage/app/public');
$link = realpath(__DIR__ . '/../..') . '/storage';

echo "Tentative via shell_exec...\n";
echo "Cible : $target\n";
echo "Lien : $link\n\n";

if (file_exists($link)) {
    shell_exec("rm -rf " . escapeshellarg($link));
}

$cmd = "ln -s " . escapeshellarg($target) . " " . escapeshellarg($link);
echo "Exécution de : $cmd\n";
$output = shell_exec($cmd . " 2>&1");
echo "Retour : " . ($output ?: "Aucun retour (OK ?)") . "\n";

if (file_exists($link) && is_link($link)) {
    echo "SUCCÈS : Le lien a été créé avec shell_exec ! Maya.\n";
} else {
    echo "ÉCHEC : shell_exec n'a pas pu créer le lien.\n";
    echo "PASSAGE AU PLAN C : Création du Proxy d'images...\n";
}
