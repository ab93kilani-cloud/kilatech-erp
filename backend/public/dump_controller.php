<?php

$file = __DIR__ . '/../app/Http/Controllers/Api/VitrinerieController.php';

header('Content-Type: text/plain');

if (!file_exists($file)) {
    echo "Fichier inexistant : " . $file;
    exit;
}

echo file_get_contents($file);
