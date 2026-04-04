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

Route::post('/public/orders', [\App\Http\Controllers\Api\OrderController::class, 'publicStore']);
Route::get('/public/quotes/{id}/pdf', [\App\Http\Controllers\Api\QuoteController::class, 'generatePDF']);

// Maya: Login moved here to ensure web interface connectivity.
Route::post('/login', [\App\Http\Controllers\AuthController::class, 'login']);

Route::get('/', function () {
    return view('welcome');
});
