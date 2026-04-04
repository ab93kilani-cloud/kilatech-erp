<?php
// Maya Login Path Tester
// Place this in /public/maya_login_test.php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Maya Login Tester 🔑</h1>";

$url = url('/login');
echo "<p>URL générée par Laravel pour 'login' : <b>$url</b></p>";

$routes = Route::getRoutes();
$loginRoute = $routes->getByName('login') ?? $routes->getByAction('App\Http\Controllers\AuthController@login');

if ($loginRoute) {
    echo "<p>Route Login trouvée !</p>";
    echo "<ul>";
    echo "<li>URI : " . $loginRoute->uri() . "</li>";
    echo "<li>Méthodes : " . implode('|', $loginRoute->methods()) . "</li>";
    echo "<li>Middlewares : " . implode(', ', $loginRoute->gatherMiddleware()) . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color:red'>ERREUR : Aucune route de login détectée !</p>";
}

echo "<h2>Test de détection de chemin :</h2>";
echo "Path courant : " . request()->path() . "<br>";
echo "Base URL : " . request()->getBaseUrl() . "<br>";
