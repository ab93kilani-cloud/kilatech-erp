<?php
// Script de secours pour vider le cache sur Hostinger
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use Illuminate\Support\Facades\Artisan;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "<h1>Nettoyage du serveur Kilatech...</h1>";

try {
    Artisan::call('route:clear');
    echo "<p>✅ Cache des routes : NETTOYÉ</p>";
    
    Artisan::call('cache:clear');
    echo "<p>✅ Cache général : NETTOYÉ</p>";
    
    Artisan::call('config:clear');
    echo "<p>✅ Configuration : NETTOYÉ</p>";

    Artisan::call('view:clear');
    echo "<p>✅ Vues : NETTOYÉ</p>";

} catch (Exception $e) {
    echo "<p>❌ Erreur : " . $e->getMessage() . "</p>";
}

echo "<br><p>Le serveur est maintenant à jour. Vous pouvez fermer cette page.</p>";
