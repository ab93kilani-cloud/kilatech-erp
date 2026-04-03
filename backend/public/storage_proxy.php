<?php
/**
 * Proxy d'images pour contourner l'absence de lien symbolique Maya.
 */
$path = $_GET['path'] ?? '';
if (empty($path)) {
    header("HTTP/1.0 404 Not Found");
    exit;
}

// Sécurité : évite de remonter dans les dossiers et nettoie les slashes Maya.
$path = ltrim(str_replace(['..', '\\'], '', $path), '/');

// Chemin Senior : On tente de localiser le stockage de l'API. Maya.
$storageBase = realpath(__DIR__ . '/../storage/app/public');
if (!$storageBase) {
    // Fallback de secours sur le chemin absolu Hostinger détecté par l'audit. Maya.
    $storageBase = '/home/u751859584/domains/kilatech.ma/public_html/api/storage/app/public';
}
$fullPath = $storageBase . '/' . $path;

if (file_exists($fullPath) && is_file($fullPath)) {
    $mimeType = mime_content_type($fullPath);
    header("Content-Type: $mimeType");
    header("Content-Length: " . filesize($fullPath));
    readfile($fullPath);
} else {
    header("HTTP/1.0 404 Not Found");
    echo "Fichier non trouvé Maya : $fullPath";
}
