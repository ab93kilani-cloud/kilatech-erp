<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain');

echo "--- Vérification du Schéma de la Base de Données ---\n\n";

$tables = ['quotes', 'clients', 'quote_lines'];

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        echo "Table [$table] : EXISTS\n";
        $columns = Schema::getColumnListing($table);
        echo "Colonnes : " . implode(', ', $columns) . "\n\n";
    } else {
        echo "Table [$table] : MISSING\n";
    }
}

try {
    $dbName = DB::connection()->getDatabaseName();
    echo "Base de données connectée : $dbName\n";
} catch (\Exception $e) {
    echo "ERREUR DE CONNEXION : " . $e->getMessage() . "\n";
}
