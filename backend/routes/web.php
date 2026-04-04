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

// Maya: Public order route moved from api.php to web.php to bypass Sanctum 401 issues.
Route::post('/public/orders', [\App\Http\Controllers\Api\OrderController::class, 'publicStore']);
