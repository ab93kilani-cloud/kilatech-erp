<?php
/**
 * Expert DevOps: Run Artisan Migration on Hostinger Maya.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Response;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "=== MIGRATION DES CATÉGORIES Maya ===\n\n";

try {
    Artisan::call('migrate', ['--force' => true]);
    echo Artisan::output();
    echo "\n\nMigration terminée avec succès ! ✅ Maya.";
} catch (\Exception $e) {
    echo "Erreur lors de la migration : " . $e->getMessage() . " ❌";
}
