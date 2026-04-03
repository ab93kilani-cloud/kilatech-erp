<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
*/

// Public Routes
Route::post('/login', [AuthController::class, 'login']);
Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class)->only(['index', 'show']);

// Public Blog Routes
Route::get('/public/articles', [\App\Http\Controllers\Api\ArticleController::class, 'publicList']);
Route::get('/public/articles/{slug}', [\App\Http\Controllers\Api\ArticleController::class, 'publicShow']);
Route::get('/public/catalogues', [\App\Http\Controllers\Api\CatalogueController::class, 'index']);
Route::post('/public/search-miss', [\App\Http\Controllers\Api\MissingSearchController::class, 'log']);
Route::post('/public/quotes', [\App\Http\Controllers\Api\QuoteController::class, 'publicStore']);
Route::get('/public/quotes/{id}/pdf', [\App\Http\Controllers\Api\QuoteController::class, 'generatePDF']);

// TEST ROUTE PUBLIC
Route::get('/test-pdf-credit/{id}', [\App\Http\Controllers\Api\CreditNoteController::class, 'generatePDF']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);

    // User & Team Management (Admin/Gérant only)
    Route::middleware('role:Admin,Gérant')->group(function () {
        Route::get('roles', [\App\Http\Controllers\Api\UserController::class, 'roles']);
        Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    });
    Route::post('products/{product}/generate-marketing', [\App\Http\Controllers\Api\ProductController::class, 'generateMarketing']);
    Route::get('products/{product}/seo-diagnostic', [\App\Http\Controllers\Api\ProductController::class, 'seoDiagnostic']);
    Route::get('products/{product}/social-diagnostic', [\App\Http\Controllers\Api\ProductController::class, 'socialDiagnostic']);
    
    // Blog & Marketing
    Route::middleware('role:Admin,Gérant,Responsable Marketing')->group(function () {
        Route::post('upload', [\App\Http\Controllers\Api\UploadController::class, 'upload']);
        Route::post('articles/generate', [\App\Http\Controllers\Api\ArticleController::class, 'generate']);
        Route::post('articles/generate-news', [\App\Http\Controllers\Api\ArticleController::class, 'generateNews']);
        Route::patch('articles/{article}/toggle-status', [\App\Http\Controllers\Api\ArticleController::class, 'toggleStatus']);
        Route::apiResource('articles', \App\Http\Controllers\Api\ArticleController::class);
        
        Route::get('marketing/config-status', [\App\Http\Controllers\Api\MissingSearchController::class, 'configStatus']);
        Route::post('marketing/repair-db', [\App\Http\Controllers\Api\MissingSearchController::class, 'repairDb']);
        Route::apiResource('missing-searches', \App\Http\Controllers\Api\MissingSearchController::class)->only(['index', 'destroy']);
    });

    // Catalog & Suppliers
    Route::get('products/next-reference', [\App\Http\Controllers\Api\ProductController::class, 'getNextReference']);
    Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class)->except(['index', 'show']);
    Route::apiResource('product-variants', \App\Http\Controllers\Api\ProductVariantController::class);
    Route::post('suppliers/import', [\App\Http\Controllers\Api\SupplierController::class, 'import']);
    Route::apiResource('suppliers', \App\Http\Controllers\Api\SupplierController::class);
    Route::apiResource('product-suppliers', \App\Http\Controllers\Api\ProductSupplierController::class);
    Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class)->only(['index', 'store']);

    // Procurement & Inventory
    Route::post('purchase-orders/{purchaseOrder}/confirm', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'confirm']);
    Route::get('purchase-orders/{purchase_order}/pdf', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'generatePDF']);
    Route::apiResource('purchase-orders', \App\Http\Controllers\Api\PurchaseOrderController::class);
    
    Route::apiResource('receipts', \App\Http\Controllers\Api\ReceiptController::class);
    
    Route::post('stock-units/validate-qa', [\App\Http\Controllers\Api\StockUnitController::class, 'validateQA']);
    Route::apiResource('stock-units', \App\Http\Controllers\Api\StockUnitController::class);

    // Sales & Finance (Commercial + Admin/Gérant)
    Route::middleware('role:Admin,Gérant,Commercial')->group(function () {
        Route::apiResource('clients', \App\Http\Controllers\Api\ClientController::class);
        
        Route::post('quotes/{quote}/lock', [\App\Http\Controllers\Api\QuoteController::class, 'lock']);
        Route::post('quotes/{quote}/unlock', [\App\Http\Controllers\Api\QuoteController::class, 'unlock']);
        Route::post('quotes/{quote}/confirm', [\App\Http\Controllers\Api\QuoteController::class, 'confirm']);
        Route::get('quotes/{quote}/pdf', [\App\Http\Controllers\Api\QuoteController::class, 'generatePDF']);
        Route::apiResource('quotes', \App\Http\Controllers\Api\QuoteController::class);
        
        Route::post('orders/from-quote', [\App\Http\Controllers\Api\OrderController::class, 'storeFromQuote']);
        Route::post('orders/{order}/revert', [\App\Http\Controllers\Api\OrderController::class, 'revertToQuote']);
        Route::post('orders/{order}/generate-invoice', [\App\Http\Controllers\Api\OrderController::class, 'generateInvoice']);
        Route::get('orders/{order}/pdf', [\App\Http\Controllers\Api\OrderController::class, 'generatePDF']);
        Route::apiResource('orders', \App\Http\Controllers\Api\OrderController::class);

        Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Api\InvoiceController::class, 'generatePDF']);
        Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class)->only(['index', 'show', 'destroy']);
        Route::apiResource('invoice-payments', \App\Http\Controllers\Api\InvoicePaymentController::class)->except(['update', 'destroy']);
        Route::get('credit-notes/{credit_note}/pdf', [\App\Http\Controllers\Api\CreditNoteController::class, 'generatePDF']);
        Route::apiResource('credit-notes', \App\Http\Controllers\Api\CreditNoteController::class)->only(['index', 'store']);
    });

    // Notifications & Alerts
    Route::get('notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::post('notifications/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

    // Accounting Space
    Route::middleware('role:Admin,Gérant,Comptable')->group(function () {
        Route::get('accounting/dashboard', [\App\Http\Controllers\Api\AccountingController::class, 'dashboard']);
        Route::post('accounting/bank-statements', [\App\Http\Controllers\Api\AccountingController::class, 'uploadBankStatement']);
        Route::post('suppliers/{supplier}/tax-certificate', [\App\Http\Controllers\Api\AccountingController::class, 'uploadSupplierCertificate']);
    });
    Route::get('documents/{path}', [\App\Http\Controllers\Api\FileController::class, 'download'])->where('path', '.*');

    // Strategic Manager Space
    Route::middleware('role:Admin,Gérant')->group(function () {
        Route::get('manager/stats', [\App\Http\Controllers\Api\ManagerController::class, 'stats']);
        Route::get('manager/performance', [\App\Http\Controllers\Api\ManagerController::class, 'performance']);
        Route::get('manager/catalogues', [\App\Http\Controllers\Api\CatalogueController::class, 'managerIndex']);
        Route::post('manager/catalogues', [\App\Http\Controllers\Api\CatalogueController::class, 'store']);
        Route::delete('manager/catalogues/{catalogue}', [\App\Http\Controllers\Api\CatalogueController::class, 'destroy']);
        Route::patch('manager/catalogues/{catalogue}/toggle', [\App\Http\Controllers\Api\CatalogueController::class, 'toggleStatus']);
    });
    // Marketing Hub
    Route::get('marketing/config-status', [\App\Http\Controllers\Api\MissingSearchController::class, 'configStatus']);
    Route::post('marketing/repair-db', [\App\Http\Controllers\Api\MissingSearchController::class, 'repairDb']);
    Route::apiResource('missing-searches', \App\Http\Controllers\Api\MissingSearchController::class)->only(['index', 'destroy']);
});
