<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/robots.txt', function () {
    $host = request()->getHost();
    if (str_starts_with($host, 'app.')) {
        return response("User-agent: *\nDisallow: /", 200)->header('Content-Type', 'text/plain');
    }
    return response("User-agent: *\nAllow: /\nSitemap: https://kilatech.ma/sitemap.xml", 200)->header('Content-Type', 'text/plain');
});

Route::get('/', function () {
    return view('welcome');
});

// Maya: Compatibility routes to handle both /api/login and /login (Fix for Hostinger prefixing).
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);
Route::post('/api/login', [\App\Http\Controllers\AuthController::class, 'login']);

Route::post('/public/orders', [\App\Http\Controllers\Api\OrderController::class, 'publicStore']);
Route::post('/api/public/orders', [\App\Http\Controllers\Api\OrderController::class, 'publicStore']);

// Maya: Nouveau workflow B2B — Demande de devis depuis la vitrine (double préfixe Hostinger)
Route::post('/public/quote-request', [\App\Http\Controllers\Api\QuoteController::class, 'publicQuoteStore']);
Route::post('/api/public/quote-request', [\App\Http\Controllers\Api\QuoteController::class, 'publicQuoteStore']);

Route::post('/public/contact', [\App\Http\Controllers\Api\ContactController::class, 'store']);
Route::post('/api/public/contact', [\App\Http\Controllers\Api\ContactController::class, 'store']);

Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class)->only(['index', 'show']);
Route::get('/api/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
Route::get('/api/api/products', [\App\Http\Controllers\Api\ProductController::class, 'index']); // Maya: Double prefix fix for Hostinger index.
Route::get('/api/products/{id}', [\App\Http\Controllers\Api\ProductController::class, 'show']); // Use {id} instead of {product} to avoid implicit binding issues if needed.
Route::get('/api/api/products/{id}', [\App\Http\Controllers\Api\ProductController::class, 'show']); // Maya: Double prefix fix for Hostinger show.

// SEO V2 - Elite products for n8n
Route::get('/seo-v2/elite-products', [\App\Http\Controllers\Api\SeoController::class, 'eliteProductsV2']);
Route::get('/api/seo-v2/elite-products', [\App\Http\Controllers\Api\SeoController::class, 'eliteProductsV2']);
