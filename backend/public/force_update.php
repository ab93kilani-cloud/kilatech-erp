<?php
/**
 * FORCE UPDATE SCRIPT - KILATECH SEO
 * This script forces the update of the API routes and SeoController directly on the server.
 */

$token = $_GET['token'] ?? '';
if ($token !== 'KILATECH_7382') {
    die("Unauthorized");
}

$apiPath = __DIR__ . '/../routes/api.php';
$controllerPath = __DIR__ . '/../app/Http/Controllers/Api/SeoController.php';
$modelPath = __DIR__ . '/../app/Models/Product.php';

// 1. Update routes/api.php
$apiContent = file_get_contents($apiPath);
if (strpos($apiContent, 'seo-v2/audit/{id}') === false) {
    $newRoute = "Route::post('seo-v2/update/{id}', [\App\Http\Controllers\Api\SeoController::class, 'updateV2']);\nRoute::get('seo-v2/audit/{id}', [\App\Http\Controllers\Api\SeoController::class, 'auditV2']);";
    $apiContent = str_replace("Route::post('seo-v2/update/{id}', [\App\Http\Controllers\Api\SeoController::class, 'updateV2']);", $newRoute, $apiContent);
    file_put_contents($apiPath, $apiContent);
    echo "✅ Routes updated.<br>";
} else {
    echo "ℹ️ Routes already up to date.<br>";
}

// 2. Update SeoController.php
$controllerContent = file_get_contents($controllerPath);
if (strpos($controllerContent, 'public function auditV2') === false) {
    // We need to inject the method before the last closing brace or specific point
    $methodCode = "\n    public function auditV2(Request \$request, \$id)\n    {\n        if (\$request->input('api_token') !== 'KILATECH_7382') {\n            return response()->json(['error' => 'Unauthorized'], 401);\n        }\n\n        \$product = Product::findOrFail(\$id);\n        \n        \$data = [\n            'seo_title' => \$product->seo_title,\n            'seo_description' => \$product->seo_description,\n            'seo_content' => \$product->seo_content,\n        ];\n\n        \$auditResult = \$this->calculateSeoScore(\$data);\n\n        \$diagnostic = \"RAPPORT D'AUDIT TECHNIQUE - PRODUIT ID: {\$product->id}\\n\";\n        \$diagnostic .= \"--------------------------------------------------\\n\";\n        \$diagnostic .= \"NOMBRE DE MOTS : {\$auditResult['word_count']}\\n\";\n        \$diagnostic .= \"SEO LOCAL (MAROC) : \" . (\$auditResult['local_seo'] ? 'OUI ✅' : 'NON ❌') . \"\\n\";\n        \$diagnostic .= \"VILLES DÉTECTÉES : \" . (empty(\$auditResult['detected_cities']) ? 'Aucune' : implode(', ', \$auditResult['detected_cities'])) . \"\\n\";\n        \$diagnostic .= \"STRUCTURE HTML (H2/H3) : \" . (\$auditResult['has_headings'] ? 'OUI ✅' : 'NON ❌') . \"\\n\";\n        \$diagnostic .= \"SCORE SEO ACTUEL : {\$auditResult['score']}/100\\n\";\n        \n        if (!empty(\$auditResult['warnings'])) {\n            \$diagnostic .= \"\\nPOINTS À AMÉLIORER :\\n- \" . implode(\"\\n- \", \$auditResult['warnings']);\n        }\n\n        return response()->json([\n            'success' => true,\n            'product_id' => \$product->id,\n            'audit' => \$auditResult,\n            'text' => \$diagnostic\n        ]);\n    }\n";
    
    // Inject before the end of the class
    $pos = strrpos($controllerContent, '}');
    if ($pos !== false) {
        $controllerContent = substr_replace($controllerContent, $methodCode, $pos, 0);
        file_put_contents($controllerPath, $controllerContent);
        echo "✅ SeoController updated.<br>";
    }
} else {
    echo "ℹ️ SeoController already up to date.<br>";
}

// 3. Update Product.php (for full_image_url)
$modelContent = file_get_contents($modelPath);
if (strpos($modelContent, 'full_image_url') === false) {
    // Add appends and accessors
    // This part is more complex to automate perfectly but we'll try a simple injection
    // Actually, if they do a git pull it's better. But if they can't, we need this.
    echo "⚠️ Product model might need manual update if Git Pull fails.<br>";
}

echo "<br><b>Operation Complete. Try executing n8n Auditor now.</b>";
