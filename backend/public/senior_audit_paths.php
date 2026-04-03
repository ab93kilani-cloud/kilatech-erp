<?php
/**
 * Senior Final Diagnostic: Absolute Path Audit. Maya.
 */
header('Content-Type: text/plain');

echo "--- Senior Audit 2 (Absolute Paths) ---\n";
echo "Current script path: " . __FILE__ . "\n";
echo "Current directory (__DIR__): " . __DIR__ . "\n";

$possibleStoragePaths = [
    __DIR__ . '/../storage/app/public/products',
    __DIR__ . '/storage/app/public/products',
    '/home/u751859584/domains/kilatech.ma/api/storage/app/public/products',
    '/home/u751859584/domains/kilatech.ma/public_html/api/storage/app/public/products'
];

foreach ($possibleStoragePaths as $p) {
    echo "Checking path: $p\n";
    if (is_dir($p)) {
        echo "  - RESULT: ✅ EXISTS\n";
        $files = scandir($p);
        echo "  - FILES: " . count($files) . " found.\n";
    } else {
        echo "  - RESULT: ❌ NOT FOUND\n";
    }
}
