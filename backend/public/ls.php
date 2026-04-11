<?php
header('Content-Type: text/plain');
echo "--- Répertoire courant : " . getcwd() . " ---\n";
echo "--- Liste des fichiers ---\n";
$files = scandir('.');
foreach ($files as $file) {
    echo $file . "\n";
}
echo "\n--- Répertoire parent ---\n";
$files = scandir('..');
foreach ($files as $file) {
    echo $file . "\n";
}
