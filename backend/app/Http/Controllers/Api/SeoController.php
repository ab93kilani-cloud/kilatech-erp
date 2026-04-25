<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SeoVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;
use App\Models\User;

class SeoController extends Controller
{
    /**
     * Get products list with SEO metadata.
     */
    public function index(Request $request)
    {
        $query = Product::select([
            'id', 'name', 'reference', 'seo_title', 'seo_description', 'seo_h1', 'seo_status', 
            'seo_score', 'seo_priority', 'seo_audit', 'last_seo_update', 'slug', 'image_path', 'images', 'category',
            'gsc_position', 'gsc_ctr', 'gsc_impressions'
        ]);

        if ($request->has('status')) {
            $query->where('seo_status', $request->status);
        }

        if ($request->has('priority')) {
            $query->where('seo_priority', $request->priority);
        }

        $products = $query->paginate($request->input('per_page', 50));

        return response()->json($products);
    }

    /**
     * Get SEO statistics.
     */
    public function stats()
    {
        $stats = Product::select('seo_status', DB::raw('count(*) as total'))
            ->groupBy('seo_status')
            ->get();

        $avgScore = Product::whereNotNull('seo_score')->avg('seo_score');

        return response()->json([
            'status_distribution' => $stats,
            'average_score' => round($avgScore, 2)
        ]);
    }

    /**
     * Update SEO for a single product.
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        $validated = $request->validate([
            'seo_title' => 'nullable|string|max:255',
            'seo_alt' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string',
            'seo_content' => 'nullable|string',
            'seo_h1' => 'nullable|string|max:255',
            'seo_priority' => 'nullable|in:high,medium,low',
            'gsc_position' => 'nullable|numeric',
            'gsc_ctr' => 'nullable|numeric',
            'gsc_impressions' => 'nullable|integer',
        ]);

        // 1. Calculate Hash (SHA256)
        $contentToHash = ($validated['seo_title'] ?? '') . 
                         ($validated['seo_description'] ?? '') . 
                         ($validated['seo_content'] ?? '');
        $newHash = hash('sha256', $contentToHash);

        // 2. Archive Current Version if it exists
        if ($product->seo_hash) {
            SeoVersion::create([
                'product_id' => $product->id,
                'seo_title' => $product->seo_title,
                'seo_description' => $product->seo_description,
                'seo_content' => $product->seo_content,
                'seo_h1' => $product->seo_h1,
                'seo_hash' => $product->seo_hash,
                'seo_score' => $product->seo_score,
                'seo_audit' => $product->seo_audit,
            ]);
        }

        // 3. Perform Scoring and Audit
        $auditResult = $this->calculateSeoScore($validated);
        
        // 4. Similarity / Duplicate check
        $duplicate = Product::where('seo_hash', $newHash)
            ->where('id', '!=', $product->id)
            ->first();

        $status = 'optimized';
        if ($duplicate || ($auditResult['similarity_hint'] ?? 0) > 80) {
            $status = 'review';
            $auditResult['warnings'][] = "High similarity or exact duplicate detected.";
        }

        if (($auditResult['word_count'] ?? 0) < 150) {
            $status = 'pending';
            $auditResult['warnings'][] = "Content length is below 150 words.";
        }

        // 5. Update Product
        $product->update(array_merge($validated, [
            'seo_hash' => $newHash,
            'seo_score' => $auditResult['score'],
            'seo_status' => $status,
            'seo_audit' => $auditResult,
            'last_seo_update' => now(),
        ]));

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'seo_status' => $status,
            'seo_score' => $auditResult['score'],
            'audit' => $auditResult
        ]);
    }

    /**
     * Massive update for multiple products.
     */
    public function bulkUpdate(Request $request)
    {
        $productsData = $request->input('products', []);
        $results = [];

        foreach ($productsData as $data) {
            if (!isset($data['id'])) continue;
            
            try {
                // We reuse the logic by calling a internal process or just duplicating essentials
                // For simplicity in this demo, we'll respond with individual status
                $requestMock = new Request($data);
                $results[] = $this->update($requestMock, $data['id'])->getData();
            } catch (\Exception $e) {
                $results[] = ['id' => $data['id'], 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Logic for progressive SEO scoring.
     */
    private function calculateSeoScore($data)
    {
        $score = 0;
        $audit = [
            'word_count' => 0,
            'local_seo' => false,
            'detected_cities' => [],
            'has_headings' => false,
            'meta_ok' => false,
            'warnings' => []
        ];

        $content = $data['seo_content'] ?? '';
        $title = $data['seo_title'] ?? '';
        $description = $data['seo_description'] ?? '';

        // 1. Progressive Content Length (Max 30 pts)
        $wordCount = str_word_count(strip_tags($content));
        $audit['word_count'] = $wordCount;
        $score += min(30, round(($wordCount / 300) * 30));

        // 2. Local SEO : Maroc & Cities (Max 25 pts)
        $moroccoPattern = '/maroc|morocco/i';
        $cities = ['casablanca', 'rabat', 'tanger', 'marrakech', 'agadir', 'fez', 'meknes'];
        $foundCities = [];
        
        $fullText = $content . ' ' . $title . ' ' . $description;
        if (preg_match($moroccoPattern, $fullText)) {
            $score += 10;
            $audit['local_seo'] = true;
        }

        foreach ($cities as $city) {
            if (stripos($fullText, $city) !== false) {
                $foundCities[] = ucfirst($city);
            }
        }
        
        if (count($foundCities) > 0) {
            $score += 15;
            $audit['detected_cities'] = $foundCities;
        }

        // 3. Structure : H2, H3, Strong (Max 25 pts)
        if (preg_match('/<h[23]/i', $content)) {
            $score += 15;
            $audit['has_headings'] = true;
        }
        if (preg_match('/<strong|<b/i', $content)) {
            $score += 10;
        }

        // 4. Meta constraints (Max 20 pts)
        $titleLen = strlen($title);
        $descLen = strlen($description);
        
        if ($titleLen >= 30 && $titleLen <= 65) $score += 10;
        if ($descLen >= 120 && $descLen <= 160) $score += 10;
        
        if ($score >= 15) $audit['meta_ok'] = true;

        $audit['score'] = $score;
        return $audit;
    }

    /**
     * V2 Simplified Methods for n8n (Bypass Headers)
     */
    public function indexV2(Request $request)
    {
        if ($request->input('api_token') !== 'KILATECH_7382') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $this->index($request);
    }

    public function statsV2(Request $request)
    {
        if ($request->input('api_token') !== 'KILATECH_7382') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $this->stats();
    }

    public function updateV2(Request $request, $id)
    {
        $token = $request->header('X-SEO-TOKEN') ?: $request->input('api_token');
        
        if ($token !== 'KILATECH_7382') {
            return response()->json(['error' => 'Unauthorized - Invalid Token'], 401);
        }

        // Decode base64-encoded seo_content to bypass ModSecurity WAF on Hostinger
        // n8n sends HTML content encoded in base64 to avoid 403 on POST with HTML tags
        if ($request->has('seo_content') && $this->isBase64($request->input('seo_content'))) {
            $request->merge([
                'seo_content' => base64_decode($request->input('seo_content'))
            ]);
        }

        return $this->update($request, $id);
    }

    public function auditV2(Request $request, $id)
    {
        if ($request->input('api_token') !== 'KILATECH_7382') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $product = Product::findOrFail($id);
        
        $data = [
            'seo_title' => $product->seo_title,
            'seo_description' => $product->seo_description,
            'seo_content' => $product->seo_content,
        ];

        $auditResult = $this->calculateSeoScore($data);

        $diagnostic = "RAPPORT D'AUDIT TECHNIQUE - PRODUIT ID: {$product->id}\n";
        $diagnostic .= "--------------------------------------------------\n";
        $diagnostic .= "NOMBRE DE MOTS : {$auditResult['word_count']}\n";
        $diagnostic .= "SEO LOCAL (MAROC) : " . ($auditResult['local_seo'] ? 'OUI ✅' : 'NON ❌') . "\n";
        $diagnostic .= "VILLES DÉTECTÉES : " . (empty($auditResult['detected_cities']) ? 'Aucune' : implode(', ', $auditResult['detected_cities'])) . "\n";
        $diagnostic .= "STRUCTURE HTML (H2/H3) : " . ($auditResult['has_headings'] ? 'OUI ✅' : 'NON ❌') . "\n";
        $diagnostic .= "SCORE SEO ACTUEL : {$auditResult['score']}/100\n";
        
        if (!empty($auditResult['warnings'])) {
            $diagnostic .= "\nPOINTS À AMÉLIORER :\n- " . implode("\n- ", $auditResult['warnings']);
        }

        return response()->json([
            'success' => true,
            'product_id' => $product->id,
            'audit' => $auditResult,
            'text' => $diagnostic
        ]);
    }

    /**
     * Check if a string is valid base64 encoded.
     */
    private function isBase64(string $string): bool
    {
        if (empty($string)) return false;
        $decoded = base64_decode($string, true);
        return $decoded !== false && base64_encode($decoded) === $string;
    }
}
