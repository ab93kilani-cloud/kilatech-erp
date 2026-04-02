<?php
/**
 * Script d'audit exhaustif du serveur Hostinger Maya.
 */
header('Content-Type: text/plain');
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "RAPPORT D'AUDIT SERVEUR - " . date('Y-m-d H:i:s') . "\n";
echo "=================================================\n\n";

// 1. Informations Système
echo "1. SYSTÈME :\n";
echo "----------- \n";
echo "OS : " . php_uname() . "\n";
echo "PHP Version : " . phpversion() . "\n";
echo "CWD : " . getcwd() . "\n";
echo " symlink() activé ? : " . (function_exists('symlink') ? 'OUI' : 'NON') . "\n";
echo " shell_exec() activé ? : " . (function_exists('shell_exec') ? 'OUI' : 'NON') . "\n\n";

// 2. Vérification des Chemins Critiques
function audit_path($path, $label) {
    echo "$label ($path) :\n";
    if (file_exists($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $type = is_link($path) ? "LIEN SYMBOLIQUE" : (is_dir($path) ? "DOSSIER" : "FICHIER");
        echo "  - État : EXISTE\n";
        echo "  - Type : $type\n";
        echo "  - Permissions : $perms\n";
        if (is_link($path)) {
            echo "  - Cible : " . readlink($path) . "\n";
        }
    } else {
        echo "  - État : N'EXISTE PAS\n";
    }
    echo "\n";
}

echo "2. STRUCTURE DES DOSSIERS :\n";
echo "-------------------------- \n";
audit_path(__DIR__ . '/../../', 'Racine (public_html)');
audit_path(__DIR__ . '/../../storage', 'Lien Storage dans public_html');
audit_path(__DIR__ . '/../storage/app/public', 'Dossier Storage Public de Laravel');
audit_path(__DIR__ . '/../storage/app/public/products', 'Dossier Images Produits');

// 3. Liste des Images Produits (si dossier existe)
$productPath = __DIR__ . '/../storage/app/public/products';
if (is_dir($productPath)) {
    echo "3. LISTE DES IMAGES (10 premières) :\n";
    echo "----------------------------------- \n";
    $files = scandir($productPath);
    $count = 0;
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "  - $file (" . filesize($productPath . '/' . $file) . " octets)\n";
            $count++;
            if ($count >= 10) break;
        }
    }
    if ($count == 0) echo "  (Aucun fichier trouvé dans products)\n";
    echo "\n";
}

// 4. Test d'écriture
echo "4. TEST D'ÉCRITURE :\n";
echo "------------------ \n";
$testFile = __DIR__ . '/../../test_audit_write.txt';
if (@file_put_contents($testFile, "Audit OK " . time())) {
    echo "  - Écriture dans public_html : SUCCÈS\n";
    unlink($testFile);
} else {
    echo "  - Écriture dans public_html : ÉCHEC (Permissions ?)\n";
}
