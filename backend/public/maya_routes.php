<?php
// Maya Route Listing Script
// Place this in /public/maya_routes.php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Maya Route Explorer 🗺️</h1>";

$routeCollection = Route::getRoutes();

echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%'>";
echo "<tr style='background:#003366;color:white'><th>Method</th><th>URI</th><th>Name</th><th>Action</th></tr>";

foreach ($routeCollection as $value) {
    if (str_contains($value->uri(), 'public')) {
        echo "<tr style='background:#eefbff'>";
    } else {
        echo "<tr>";
    }
    echo "<td>" . implode('|', $value->methods()) . "</td>";
    echo "<td>" . $value->uri() . "</td>";
    echo "<td>" . $value->getName() . "</td>";
    echo "<td>" . $value->getActionName() . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>URL Actuelle détectée par Laravel : " . url('/') . "</h2>";
echo "<h2>Request Path détecté par Laravel : " . request()->path() . "</h2>";
