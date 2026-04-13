<?php
/**
 * Product Slug Generator & Data Integrity Guard.
 * Generates unique slugs for all products based on their names.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use App\Models\Product;
use Illuminate\Support\Str;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "=== KILATECH SEO PREPARATION: SLUG GENERATOR ===\n\n";

try {
    $beforeCount = Product::count();
    $missingSlugs = Product::whereNull('slug')->count();
    
    echo "📊 Initial Stats:\n";
    echo "- Total Products: $beforeCount\n";
    echo "- Products without slugs: $missingSlugs\n\n";

    echo "⚙️ Processing slugs...\n";
    
    $updatedCount = 0;
    $products = Product::whereNull('slug')->orWhere('slug', '')->get();

    foreach ($products as $product) {
        $baseSlug = Str::slug($product->name);
        if (empty($baseSlug)) {
            $baseSlug = 'product-' . $product->id;
        }
        
        $slug = $baseSlug;
        $counter = 1;
        
        // Ensure uniqueness
        while (Product::where('slug', $slug)->where('id', '!=', $product->id)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        $product->slug = $slug;
        $product->save();
        
        echo "✅ Generated: '$product->name' -> $slug\n";
        $updatedCount++;
    }

    $afterCount = Product::count();
    
    echo "\n\n=== FINAL VERIFICATION ===\n";
    echo "✅ Slugs updated: $updatedCount\n";
    echo "✅ Final Product Count: $afterCount\n";
    
    if ($beforeCount === $afterCount) {
        echo "🚀 DATA INTEGRITY CONFIRMED: No data lost during migration.\n";
    } else {
        echo "⚠️ WARNING: Item count mismatch! Check logs.\n";
    }

} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage();
}
