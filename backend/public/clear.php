<?php
// Maya Emergency Cache Clear Script
// Place this in /public/clear.php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use Illuminate\Support\Facades\Artisan;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "<h1>Maya Reset System 🔧</h1>";

try {
    echo "Clearing Routes... ";
    Artisan::call('route:clear');
    echo "OK<br>";

    echo "Clearing Config... ";
    Artisan::call('config:clear');
    echo "OK<br>";

    echo "Clearing Cache... ";
    Artisan::call('cache:clear');
    echo "OK<br>";

    echo "Clearing Views... ";
    Artisan::call('view:clear');
    echo "OK<br>";

    echo "<br><b>Système Rafraîchi ! Maya.</b><br>";
    echo "Veuillez maintenant retester votre panier.";
} catch (Exception $e) {
    echo "<br><b>Erreur :</b> " . $e->getMessage();
}
