<?php
/**
 * Senior Diagnostic: Symbolic Link Check. Maya.
 */
header('Content-Type: text/plain');
$target = __DIR__ . '/../storage/app/public';
$link = __DIR__ . '/storage';

echo "--- Senior Audit 1 (Symlink) ---\n";
echo "Target exists: " . (is_dir($target) ? 'YES' : 'NO') . "\n";
echo "Link exists: " . (file_exists($link) ? 'YES' : 'NO') . "\n";
echo "Is link: " . (is_link($link) ? 'YES' : 'NO') . "\n";
if (is_link($link)) {
    echo "Points to: " . readlink($link) . "\n";
}
echo "--- Real Path --- \n";
echo "Real path of public: " . __DIR__ . "\n";
echo "Real path of storage root: " . realpath($target) . "\n";
