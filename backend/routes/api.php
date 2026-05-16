<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ContactController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
*/

// SEO V2 - Simplified for n8n (Publicly accessible but token-protected in controller)
Route::get('seo-v2/products', [\App\Http\Controllers\Api\SeoController::class, 'indexV2']);
Route::get('seo-v2/stats', [\App\Http\Controllers\Api\SeoController::class, 'statsV2']);
Route::post('seo-v2/update/{id}', [\App\Http\Controllers\Api\SeoController::class, 'updateV2']);
Route::get('seo-v2/audit/{id}', [\App\Http\Controllers\Api\SeoController::class, 'auditV2']);
Route::post('seo-v2/articles', [\App\Http\Controllers\Api\ArticleController::class, 'storeV2']);
Route::get('seo-v2/elite-products', [\App\Http\Controllers\Api\SeoController::class, 'eliteProductsV2']);
Route::get('seo-v2/blog-list', [\App\Http\Controllers\Api\SeoController::class, 'blogListV2']);
Route::get('seo-v2/blog-detail/{slug}', [\App\Http\Controllers\Api\SeoController::class, 'blogDetailV2']);
Route::get('seo-debug', function() { return "OK"; });
// Deployment trigger: Isolation of web requests from ERP quotes.

// Public Routes (Products, Articles, Orders, Login) have been moved to web.php Maya.
// with double-prefix support for Hostinger compatibility.

// Maya: Public quote request from storefront (no auth required)
// Maya: Public quote request moved to web.php for Hostinger compatibility.

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::get('/dashboard-stats', [\App\Http\Controllers\Api\DashboardController::class, 'getStats']);

    // User & Team Management (Admin/Gérant only)
    Route::middleware('role:Admin,Gérant')->group(function () {
        Route::get('roles', [\App\Http\Controllers\Api\UserController::class, 'roles']);
        Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    });
    // Catalog & Marketing
    Route::post('products/{product}/generate-marketing', [\App\Http\Controllers\Api\ProductController::class, 'generateMarketing']);
    Route::post('products/{product}/sync-gsc', [\App\Http\Controllers\Api\ProductController::class, 'syncGsc']);
    Route::get('products/{product}/seo-diagnostic', [\App\Http\Controllers\Api\ProductController::class, 'seoDiagnostic']);
    Route::get('products/{product}/social-diagnostic', [\App\Http\Controllers\Api\ProductController::class, 'socialDiagnostic']);
    
    // Standard Catalog Routes
    Route::get('products', [\App\Http\Controllers\Api\ProductController::class, 'index']);
    
    // Blog & Marketing
    Route::middleware('role:Admin,Gérant,Responsable Marketing')->group(function () {
        Route::post('upload', [\App\Http\Controllers\Api\UploadController::class, 'upload']);
        Route::post('articles/generate', [\App\Http\Controllers\Api\ArticleController::class, 'generate']);
        Route::post('articles/generate-news', [\App\Http\Controllers\Api\ArticleController::class, 'generateNews']);
        Route::post('articles/generate-category-news', [\App\Http\Controllers\Api\ArticleController::class, 'generateCategoryNews']);
        Route::patch('articles/{article}/toggle-status', [\App\Http\Controllers\Api\ArticleController::class, 'toggleStatus']);
        Route::apiResource('articles', \App\Http\Controllers\Api\ArticleController::class);
        
        Route::get('marketing/config-status', [\App\Http\Controllers\Api\MissingSearchController::class, 'configStatus']);
        Route::post('marketing/repair-db', [\App\Http\Controllers\Api\MissingSearchController::class, 'repairDb']);
        Route::apiResource('missing-searches', \App\Http\Controllers\Api\MissingSearchController::class)->only(['index', 'destroy']);
        
    });

    // SEO Automation (n8n Interface) - Secured for Bot & Marketing
    Route::prefix('seo')->middleware([
        'role:Admin,Gérant,Responsable Marketing,n8n_bot', 
        'seo_log', 
        'throttle:60,1'
    ])->group(function () {
        Route::get('products', [\App\Http\Controllers\Api\SeoController::class, 'index']);
        Route::get('stats', [\App\Http\Controllers\Api\SeoController::class, 'stats']);
        Route::patch('products/{id}', [\App\Http\Controllers\Api\SeoController::class, 'update']);
        Route::post('products/bulk', [\App\Http\Controllers\Api\SeoController::class, 'bulkUpdate']);
    });

    // Catalog & Suppliers
    Route::get('products/next-reference', [\App\Http\Controllers\Api\ProductController::class, 'getNextReference']);
    Route::apiResource('products', \App\Http\Controllers\Api\ProductController::class)->except(['index']);
    Route::apiResource('product-variants', \App\Http\Controllers\Api\ProductVariantController::class);
    Route::post('suppliers/import', [\App\Http\Controllers\Api\SupplierController::class, 'import']);
    Route::apiResource('suppliers', \App\Http\Controllers\Api\SupplierController::class);
    Route::apiResource('product-suppliers', \App\Http\Controllers\Api\ProductSupplierController::class);
    Route::post('categories/{category}/generate-presentation', [\App\Http\Controllers\Api\CategoryController::class, 'generatePresentation']);
    Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);

    // Procurement & Inventory
    Route::post('purchase-orders/{purchaseOrder}/confirm', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'confirm']);
    Route::post('purchase-orders/{purchaseOrder}/archive', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'archive']);
    Route::get('purchase-orders/{purchase_order}/pdf', [\App\Http\Controllers\Api\PurchaseOrderController::class, 'generatePDF']);
    Route::apiResource('purchase-orders', \App\Http\Controllers\Api\PurchaseOrderController::class);
    
    Route::apiResource('receipts', \App\Http\Controllers\Api\ReceiptController::class);
    Route::post('receipts/{receipt}/upload-invoice', [\App\Http\Controllers\Api\ReceiptController::class, 'uploadInvoice']);
    Route::post('receipts/{receipt}/revert', [\App\Http\Controllers\Api\ReceiptController::class, 'revertToPO']);
    Route::get('receipts/{receipt}/download-invoice', [\App\Http\Controllers\Api\ReceiptController::class, 'downloadInvoiceProxy']);
    
    Route::post('stock-units/validate-qa', [\App\Http\Controllers\Api\StockUnitController::class, 'validateQA']);
    Route::apiResource('stock-units', \App\Http\Controllers\Api\StockUnitController::class);

    // Sales & Finance (Commercial + Admin/Gérant + Comptable)
    Route::middleware('role:Admin,Gérant,Commercial,Comptable')->group(function () {
        Route::apiResource('clients', \App\Http\Controllers\Api\ClientController::class);
        
        Route::post('quotes/{quote}/lock', [\App\Http\Controllers\Api\QuoteController::class, 'lock']);
        Route::post('quotes/{quote}/unlock', [\App\Http\Controllers\Api\QuoteController::class, 'unlock']);
        Route::post('quotes/{quote}/confirm', [\App\Http\Controllers\Api\QuoteController::class, 'confirm']);
        Route::post('quotes/{quote}/confirm-and-send', [\App\Http\Controllers\Api\QuoteController::class, 'confirmAndSend']);
        Route::get('quotes/{quote}/pdf', [\App\Http\Controllers\Api\QuoteController::class, 'generatePDF']);
        Route::apiResource('quotes', \App\Http\Controllers\Api\QuoteController::class);
        
        // Vitrinerie (Tri du web - Devis, Clients, Messages)
        Route::get('vitrinerie/quotes', [\App\Http\Controllers\Api\VitrinerieController::class, 'getQuotes']);
        Route::get('vitrinerie/clients', [\App\Http\Controllers\Api\VitrinerieController::class, 'getClients']);
        Route::get('vitrinerie/messages', [\App\Http\Controllers\Api\VitrinerieController::class, 'getMessages']);
        Route::post('vitrinerie/messages/{id}/read', [\App\Http\Controllers\Api\VitrinerieController::class, 'markMessageAsRead']);
        Route::delete('vitrinerie/messages/{id}', [\App\Http\Controllers\Api\VitrinerieController::class, 'deleteMessage']);
        
        Route::post('orders/from-quote', [\App\Http\Controllers\Api\OrderController::class, 'storeFromQuote']);
        Route::post('orders/{order}/confirm-pending', [\App\Http\Controllers\Api\OrderController::class, 'confirmPending']);
        Route::post('orders/{order}/revert', [\App\Http\Controllers\Api\OrderController::class, 'revertToQuote']);
        Route::post('orders/{order}/generate-invoice', [\App\Http\Controllers\Api\OrderController::class, 'generateInvoice']);
        Route::get('orders/{order}/pdf', [\App\Http\Controllers\Api\OrderController::class, 'generatePDF']);
        Route::apiResource('orders', \App\Http\Controllers\Api\OrderController::class);

        Route::get('invoices/{invoice}/pdf', [\App\Http\Controllers\Api\InvoiceController::class, 'generatePDF']);
        Route::post('invoices/{invoice}/restore', [\App\Http\Controllers\Api\InvoiceController::class, 'restore']);
        Route::apiResource('invoices', \App\Http\Controllers\Api\InvoiceController::class)->only(['index', 'show', 'update', 'destroy']);
        Route::apiResource('invoice-payments', \App\Http\Controllers\Api\InvoicePaymentController::class)->except(['update', 'destroy']);
        Route::post('supplier-payments/{supplierPayment}/confirm', [\App\Http\Controllers\Api\SupplierPaymentController::class, 'confirm']);
        Route::apiResource('supplier-payments', \App\Http\Controllers\Api\SupplierPaymentController::class)->except(['update', 'destroy']);
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
        Route::get('accounting/finance-overview', [\App\Http\Controllers\Api\AccountingController::class, 'financeOverview']);
        Route::post('accounting/quick-update-due-date', [\App\Http\Controllers\Api\AccountingController::class, 'quickUpdateDueDate']);
        Route::post('accounting/bank-statement', [\App\Http\Controllers\Api\AccountingController::class, 'uploadBankStatement']);
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
        
        Route::get('manager/documents', [\App\Http\Controllers\Api\ManagerController::class, 'getDocuments']);
        Route::post('manager/documents', [\App\Http\Controllers\Api\ManagerController::class, 'uploadDocument']);
        Route::delete('manager/documents/{id}', [\App\Http\Controllers\Api\ManagerController::class, 'deleteDocument']);
    });
});
