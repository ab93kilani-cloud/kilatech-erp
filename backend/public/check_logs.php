<?php

$logFile = __DIR__ . '/../storage/logs/laravel.log';

header('Content-Type: text/plain');

if (!file_exists($logFile)) {
    echo "Fichier de log inexistant : " . $logFile;
    exit;
}

$lines = 50;
$data = file($logFile);
$totalLines = count($data);
$start = max(0, $totalLines - $lines);

echo "--- Dernières $lines lignes de laravel.log ---\n\n";
for ($i = $start; $i < $totalLines; $i++) {
    echo $data[$i];
}
