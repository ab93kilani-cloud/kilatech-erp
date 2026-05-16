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

Route::get('/api/test-server', function() {
    return response()->json(['status' => 'Server is Alive', 'time' => now()->toDateTimeString()]);
});

Route::get('/robots.txt', function () {
    $host = request()->getHost();
    return response("User-agent: *\nAllow: /\nSitemap: https://kilatech.ma/sitemap.xml", 200)->header('Content-Type', 'text/plain');
});

Route::get('/sitemap.xml', [\App\Http\Controllers\Api\SeoController::class, 'sitemap']);
Route::get('/api/sitemap.xml', [\App\Http\Controllers\Api\SeoController::class, 'sitemap']);

// Public Blog Articles - Direct Logic (Bypassing Controller, Auth and CORS for Hostinger)
Route::get('/api/public/articles', function(\Illuminate\Http\Request $request) {
    $articles = \App\Models\Article::where('status', 'published')
        ->when($request->category, function($q, $cat) {
            return $q->where('category', $cat);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json($articles)
        ->header('Access-Control-Allow-Origin', 'https://kilatech.ma')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization')
        ->header('Access-Control-Allow-Credentials', 'true');
})->middleware([]);

Route::get('/api/public/articles/{slug}', function($slug) {
    $article = \App\Models\Article::where('slug', $slug)
        ->where('status', 'published')
        ->firstOrFail();

    return response()->json($article)
        ->header('Access-Control-Allow-Origin', 'https://kilatech.ma')
        ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, Authorization')
        ->header('Access-Control-Allow-Credentials', 'true');
})->middleware([]);

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

// Public product catalog for kilatech.ma (no auth required)
Route::get('/api/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
Route::get('/api/products/{id}', [\App\Http\Controllers\Api\ProductController::class, 'show']);
Route::get('/api/public/products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
Route::get('/api/public/products/{id}', [\App\Http\Controllers\Api\ProductController::class, 'show']);

// SEO V2 - Elite products for n8n
Route::get('/seo-v2/elite-products', [\App\Http\Controllers\Api\SeoController::class, 'eliteProductsV2']);
Route::get('/api/seo-v2/elite-products', [\App\Http\Controllers\Api\SeoController::class, 'eliteProductsV2']);

// Public Categories (for Range Descriptions in Storefront)
Route::get('/api/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/api/api/categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
Route::get('/api/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);
Route::get('/api/api/categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);
