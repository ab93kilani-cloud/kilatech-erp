<?php
/**
 * Expert Senior DevOps: Deep Folder Audit Maya.
 */
header('Content-Type: text/plain');

function listFolder($path, $level = 0) {
    if (!is_dir($path)) return;
    $files = scandir($path);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        echo str_repeat("  ", $level) . "|-- " . $file . (is_dir($path . '/' . $file) ? " (DIR)" : "") . "\n";
        if (is_dir($path . '/' . $file) && $level < 3) {
            listFolder($path . '/' . $file, $level + 1);
        }
    }
}

echo "=== AUDIT PROFOND DES DOSSIERS Maya ===\n\n";

$paths = [
    'STORAGE APP PUBLIC' => __DIR__ . '/../storage/app/public',
    'PUBLIC ROOT' => __DIR__
];

foreach ($paths as $name => $p) {
    echo "[$name] : $p\n";
    if (is_dir($p)) {
        listFolder($p);
    } else {
        echo "❌ DOSSIER INTROUVABLE\n";
    }
    echo "\n";
}

echo "--- ANALYSE SENIOR ---\n";
$storageLink = __DIR__ . '/storage';
echo "Lien 'public/storage' : " . (file_exists($storageLink) ? (is_link($storageLink) ? "Lien symbolique ✅" : "DOSSIER RÉEL (DOUBLON ⚠️)") : "ABSENT") . "\n";
