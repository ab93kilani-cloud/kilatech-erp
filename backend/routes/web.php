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

Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class)->only(['index', 'show']);
Route::get('/api/products', [\App\Http\Controllers\Api\ProductController::class, 'index']); // Explicit double prefix fix.
