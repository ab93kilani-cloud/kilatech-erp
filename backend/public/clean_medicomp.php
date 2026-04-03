<?php
/**
 * Nettoyage définitif du doublon MEDICOMP Maya.
 */
header('Content-Type: text/plain');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "--- Nettoyage MEDICOMP Maya ---\n\n";

$deleted = \App\Models\Product::withTrashed()
    ->where('slug', 'medicomp')
    ->forceDelete();

if ($deleted) {
    echo "SUCCÈS : L'ancien produit MEDICOMP a été effacé de la mémoire de la base. ✅\n";
} else {
    echo "INFO : Aucun produit MEDICOMP n'a été trouvé pour le nettoyage.\n";
}

echo "\n--- Vous pouvez maintenant recréer votre produit sur le site ! Maya. ---\n";
