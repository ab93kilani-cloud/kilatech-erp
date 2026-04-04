<?php
// Maya Full Diagnostic Script v2
// Place this in /public/maya_diag.php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Maya Diagnostic System v2 🩺</h1>";

function test_step($name, $callback) {
    echo "<h3>Step: $name ... ";
    try {
        $result = $callback();
        echo "<span style='color:green'>PASSED ✅</span></h3>";
        if ($result) echo "<pre style='background:#f4f4f4;padding:10px'>" . print_r($result, true) . "</pre>";
    } catch (Exception $e) {
        echo "<span style='color:red'>FAILED ❌</span></h3>";
        echo "<p style='color:red;background:#fff5f5;padding:10px'><b>Error:</b> " . $e->getMessage() . "<br>";
        echo "<b>File:</b> " . $e->getFile() . " (Line: " . $e->getLine() . ")</p>";
    }
    echo "<hr>";
}

// 0. PHP Environment Maya.
test_step("PHP Environment", function() {
    return [
        'PHP Version' => PHP_VERSION,
        'Memory Limit' => ini_get('memory_limit'),
        'Upload Max' => ini_get('upload_max_filesize'),
        'Extensions' => [
            'GD' => extension_loaded('gd'),
            'MBString' => extension_loaded('mbstring'),
            'DOM' => extension_loaded('dom'),
            'XML' => extension_loaded('xml'),
            'BCMath' => extension_loaded('bcmath')
        ]
    ];
});

// 1. Database Connection Maya.
test_step("Database Connection", function() {
    // Corrected table name for test
    return DB::select('select name from categories limit 1');
});

// 2. Table Existence Maya.
test_step("Database Tables Integrity", function() {
    $tables = ['orders', 'quotes', 'order_lines', 'quote_lines', 'clients', 'product_variants', 'notifications'];
    $missing = [];
    foreach ($tables as $t) {
        if (!\Illuminate\Support\Facades\Schema::hasTable($t)) $missing[] = $t;
    }
    return count($missing) === 0 ? "All critical tables exist." : "Missing: " . implode(', ', $missing);
});

// 3. Document Numbering Logic Maya.
test_step("Document Numbering Logic", function() {
    $orderCount = \App\Models\Order::withTrashed()->count();
    $quoteCount = \App\Models\Quote::withTrashed()->count();
    return "Next Order: CMD-".date('ym')."-".($orderCount+1) . " | Next Quote: DEV-".date('ym')."-".($quoteCount+1);
});

// 4. PDF Generation (DomPDF) Maya.
test_step("PDF Generation Engine", function() {
    $pdf = Pdf::loadHTML('<h1>Maya Test PDF</h1>');
    $output = $pdf->output();
    return "PDF Generated Successfully. Size: " . strlen($output) . " bytes.";
});

// 5. Logo Access Maya.
test_step("Logo & Asset Access", function() {
    $logoPath = public_path('images/logo.png');
    if (file_exists($logoPath)) {
        return "Logo exists at $logoPath (Size: " . filesize($logoPath) . ")";
    } else {
        return "Logo NOT FOUND at $logoPath (Fallback will be used)";
    }
});

echo "<h2>Diagnostic v2 terminé. Maya.</h2>";
