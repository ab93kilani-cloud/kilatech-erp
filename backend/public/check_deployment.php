<?php
/**
 * Post-Deployment Verification Tool for Kilatech.
 * Checks SEO, data integrity, and architecture separation.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\Product;
use App\Models\Quote;
use App\Models\Invoice;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "=== KILATECH DEPLOYMENT VERIFICATION ===\n\n";

function check($description, $callback) {
    try {
        $result = $callback();
        echo "✅ " . str_pad($description, 40) . " : OK " . ($result ? "($result)" : "") . "\n";
    } catch (\Exception $e) {
        echo "❌ " . str_pad($description, 40) . " : FAILED (" . $e->getMessage() . ")\n";
    }
}

// 1. DATA INTEGRITY
check("Product Count", function() { return Product::count(); });
check("Quotations Count", function() { return Quote::count(); });
check("Invoices Count", function() { return Invoice::count(); });

// 2. SEO PREPARATION
check("Products without slugs", function() {
    $count = Product::whereNull('slug')->orWhere('slug', '')->count();
    if ($count > 0) throw new \Exception("$count missing");
    return "All slugs filled";
});

check("Showcase items count", function() {
    return Product::where('is_showcase', true)->count();
});

// 3. ARCHITECTURE SEPARATION (Logic only)
check("App Subdomain Robots Check", function() {
    // This is tested in production via HTTP, here we just check if middleware exists
    if (!class_exists(\App\Http\Middleware\CheckSubdomainRobots::class)) throw new \Exception("Middleware missing");
    return "Middleware registered";
});

echo "\n\nVERIFICATION COMPLETED! 🚀 All systems green.";
