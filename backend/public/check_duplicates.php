<?php
/**
 * Diagnostic de doublons Maya.
 */
header('Content-Type: text/plain');
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "--- Recherche de MEDICOMP en base Maya ---\n\n";

$products = \App\Models\Product::withTrashed()
    ->where('name', 'LIKE', '%MEDICOMP%')
    ->orWhere('slug', 'LIKE', '%medicomp%')
    ->get();

if ($products->count() > 0) {
    foreach ($products as $p) {
        echo "ID: {$p->id} | Nom: {$p->name} | Slug: {$p->slug} | Supprimé: " . ($p->trashed() ? "OUI" : "NON") . "\n";
    }
} else {
    echo "Aucun produit trouvé avec ce nom (même supprimé).";
}

echo "\n--- Conseil Senior ---\n";
echo "Si un produit existe avec 'Supprimé: OUI', le système bloque la recréation avec le même identifiant.\n";
