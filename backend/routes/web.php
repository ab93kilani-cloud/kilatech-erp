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

// Maya:// Public routes moved to web.php Maya.
Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class)->only(['index', 'show']);
Route::get('/public/articles', [\App\Http\Controllers\Api\ArticleController::class, 'publicList']);
Route::get('/public/articles/{slug}', [\App\Http\Controllers\Api\ArticleController::class, 'publicShow']);
Route::get('/public/catalogues', [\App\Http\Controllers\Api\CatalogueController::class, 'index']);
Route::post('/public/search-miss', [\App\Http\Controllers\Api\MissingSearchController::class, 'log']);
Route::post('/public/orders', [\App\Http\Controllers\Api\OrderController::class, 'publicStore']);
Route::get('/public/quotes/{id}/pdf', [\App\Http\Controllers\Api\QuoteController::class, 'generatePDF']);
