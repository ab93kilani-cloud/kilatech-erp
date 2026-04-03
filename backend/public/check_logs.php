<?php
/**
 * Lecteur de logs Laravel pour Hostinger Maya.
 */
header('Content-Type: text/plain');
$logFile = __DIR__ . '/../storage/logs/laravel.log';

if (file_exists($logFile)) {
    echo "--- Dernières 50 lignes de laravel.log Maya ---\n\n";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -50);
    echo implode("", $lastLines);
} else {
    echo "Fichier de log introuvable à l'adresse : $logFile";
}
