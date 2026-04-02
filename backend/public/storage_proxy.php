<?php
/**
 * Proxy d'images pour contourner l'absence de lien symbolique Maya.
 */
$path = $_GET['path'] ?? '';
if (empty($path)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Sécurité : évite de remonter dans les dossiers
$path = str_replace(['..', '\\'], '', $path);
$fullPath = __DIR__ . '/../storage/app/public/' . $path;

if (file_exists($fullPath) && is_file($fullPath)) {
    $mimeType = mime_content_type($fullPath);
    header("Content-Type: $mimeType");
    header("Content-Length: " . filesize($fullPath));
    readfile($fullPath);
} else {
    header("HTTP/1.0 404 Not Found");
    echo "Fichier non trouvé Maya : $fullPath";
}
