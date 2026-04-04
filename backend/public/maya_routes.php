<?php
// Maya Route Explorer v2 (Includes Middlewares)
// Place this in /public/maya_routes.php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Maya Route Explorer v2 (Middlewares) 🗺️</h1>";

$routeCollection = Route::getRoutes();

echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%'>";
echo "<tr style='background:#003366;color:white'><th>Method</th><th>URI</th><th>Name</th><th>Middlewares</th></tr>";

foreach ($routeCollection as $value) {
    if (str_contains($value->uri(), 'public')) {
        echo "<tr style='background:#eefbff'>";
    } else {
        echo "<tr>";
    }
    echo "<td>" . implode('|', $value->methods()) . "</td>";
    echo "<td>" . $value->uri() . "</td>";
    echo "<td>" . $value->getName() . "</td>";
    echo "<td>" . implode(', ', $value->gatherMiddleware()) . "</td>";
    echo "</tr>";
}
echo "</table>";
