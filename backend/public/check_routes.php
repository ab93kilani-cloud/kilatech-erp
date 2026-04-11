<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: text/plain');
echo "--- Liste des routes enregistrées ---\n\n";

$routes = Route::getRoutes();

foreach ($routes as $route) {
    if (str_contains($route->uri(), 'confirm-and-send')) {
        echo "[" . implode('|', $route->methods()) . "] " . $route->uri() . " -> " . $route->getActionName() . "\n";
    }
}

if (empty($routes)) {
    echo "Aucune route trouvée.";
}
