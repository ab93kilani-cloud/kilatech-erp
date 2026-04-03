<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::with(['variants', 'supplier'])->get());
    }

    public function getNextReference()
    {
        $lastId = DB::table('products')->max('id') ?? 1000;
        return response()->json([
            'reference' => 'PRD-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT)
        ]);
    }

    public function store(Request $request, \App\Services\MarketingAiService $marketingAi)
    {
        if (empty($request->input('reference'))) {
            $lastId = DB::table('products')->max('id') ?? 1000;
            $request->merge(['reference' => 'PRD-' . str_pad($lastId + 1, 5, '0', STR_PAD_LEFT)]);
        }

        $request->validate([
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('products', 'reference')->whereNull('deleted_at')],
            'supplier_id' => 'nullable|exists:suppliers,id',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->whereNull('deleted_at')],
            'category' => 'required|string|max:255',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'social_linkedin_post' => 'nullable|string',
            'social_facebook_post' => 'nullable|string',
            'social_instagram_post' => 'nullable|string',
            'local_geo_context' => 'nullable|string',
            'is_medical' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $variantsData = json_decode($request->input('variants', '[]'), true);
        
        // Auto-generate SKUs internally if they are missing
        $ref = $request->input('reference', 'PRD');
        foreach ($variantsData as $index => &$v) {
            if (empty(trim($v['sku'] ?? ''))) {
                $v['sku'] = $ref . '-V' . str_pad($index + 1, 2, '0', STR_PAD_LEFT);
            }
            
            $exists = \App\Models\ProductVariant::where('sku', trim($v['sku']))->whereNull('deleted_at')->exists();
            if ($exists) {
                // Fallback to random to avoid infinite failures
                $v['sku'] = $ref . '-V' . strtoupper(Str::random(4));
            }
        }
        unset($v);

        DB::beginTransaction();
        try {
            $productData = $request->except(['image', 'variants', 'deleted_variant_ids']);
            $product = Product::create($productData);

            // AUTO-GENERATE MARKETING CONTENT WITH AI if fields are blank
            if (empty($product->meta_title)) {
                $content = $marketingAi->generateForProduct($product);
                $product->update($content);
            }

            // NEW: AUTO-GENERATE BLOG ARTICLE
            try {
                $articleData = $marketingAi->generateArticleForProduct($product);
                $article = \App\Models\Article::create($articleData);
                
                // CREATE NOTIFICATION
                \App\Models\Notification::create([
                    'type' => 'blog_review',
                    'title' => 'Nouveau blog à relire',
                    'message' => 'Un article pour "' . $product->name . '" a été généré automatiquement.',
                    'action_url' => '/admin/marketing/articles/' . $article->id . '/edit',
                    'is_read' => false
                ]);
            } catch (\Exception $e) {
                // Don't fail product creation if blog generation fails, but log it
                \Illuminate\Support\Facades\Log::error('Auto blog generation failed: ' . $e->getMessage());
            }

            foreach ($variantsData as $v) {
                $product->variants()->create($v);
            }

            if ($request->hasFile('image')) {
                $this->processAndStoreImage($request->file('image'), $product);
            }

            DB::commit();
            return response()->json($product->load('variants'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['variants', 'supplier']));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        if (empty($request->input('reference'))) {
            if (empty($product->reference)) {
                $request->merge(['reference' => 'PRD-' . str_pad($product->id, 5, '0', STR_PAD_LEFT)]);
            } else {
                $request->merge(['reference' => $product->reference]);
            }
        }

        $request->validate([
            'reference' => ['nullable', 'string', 'max:100', Rule::unique('products', 'reference')->ignore($product->id)->whereNull('deleted_at')],
            'supplier_id' => 'nullable|exists:suppliers,id',
            'name' => 'string|max:255',
            'brand' => 'nullable|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)->whereNull('deleted_at')],
            'category' => 'string|max:255',
            'description' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'social_linkedin_post' => 'nullable|string',
            'social_facebook_post' => 'nullable|string',
            'social_instagram_post' => 'nullable|string',
            'local_geo_context' => 'nullable|string',
            'is_medical' => 'boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $variantsInput = $request->input('variants', '[]');
        $variantsData = is_array($variantsInput) ? $variantsInput : json_decode((string)$variantsInput, true);
        
        $deletedIdsInput = $request->input('deleted_variant_ids', '[]');
        $deletedIds = is_array($deletedIdsInput) ? $deletedIdsInput : json_decode((string)$deletedIdsInput, true);

        // Auto-generate SKUs internally if they are missing
        $ref = $request->input('reference', 'PRD');
        foreach ($variantsData as &$v) {
            if (empty(trim($v['sku'] ?? ''))) {
                $v['sku'] = $ref . '-V' . strtoupper(Str::random(4));
            }
            
            $query = \App\Models\ProductVariant::where('sku', trim($v['sku']))->whereNull('deleted_at');
            if (!empty($v['id'])) {
                $query->where('id', '!=', $v['id']);
            }
            if ($query->exists()) {
                $v['sku'] = $ref . '-V' . strtoupper(Str::random(5));
            }
        }
        unset($v);

        DB::beginTransaction();
        try {
            $product->update($request->except(['image', 'variants', 'deleted_variant_ids']));

            if (!empty($deletedIds)) {
                $product->variants()->whereIn('id', $deletedIds)->delete();
            }

            foreach ($variantsData as $v) {
                if (!empty($v['id'])) {
                    $product->variants()->where('id', $v['id'])->update([
                        'sku' => trim($v['sku']),
                        'name_variation' => $v['name_variation'] ?? null,
                        'cost_price' => $v['cost_price'] ?? null,
                        'price_ht' => $v['price_ht'] ?? null,
                        'tva_rate' => collect($v)->has('tva_rate') ? $v['tva_rate'] : 20,
                        'public_price' => $v['public_price'] ?? null,
                        'margin_percent' => $v['margin_percent'] ?? 0,
                    ]);
                } else {
                    $product->variants()->create($v);
                }
            }

            if ($request->hasFile('image')) {
                $this->processAndStoreImage($request->file('image'), $product);
            }

            DB::commit();
            return response()->json($product->load('variants'));
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(Product $product)
    {
        $product->delete(); // Soft delete via Eloquent
        return response()->json(null, 204);
    }

    public function generateMarketing(Product $product, \App\Services\MarketingAiService $marketingAi)
    {
        $content = $marketingAi->generateForProduct($product);
        $product->update($content);
        return response()->json($product);
    }

    public function seoDiagnostic(Product $product, \App\Services\MarketingAiService $marketingAi)
    {
        return response()->json($marketingAi->diagnoseSeo($product));
    }

    public function socialDiagnostic(Product $product, \App\Services\MarketingAiService $marketingAi)
    {
        return response()->json($marketingAi->diagnoseSocial($product));
    }

    /**
     * Process image: Store in its original format
     */
    private function processAndStoreImage($file, Product $product)
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::slug($product->name) . '-' . time() . '.' . $extension;
        
        // 1. Stockage standard dans le disque 'public' de Laravel (storage/app/public/products) Maya.
        $path = $file->storeAs('products', $fileName, 'public');
        
        // 2. Fallback Hostinger : Si le lien symbolique n'existe pas, on copie dans /public/storage. Maya.
        $publicStoragePath = public_path('storage/products');
        if (!is_dir($publicStoragePath)) {
            mkdir($publicStoragePath, 0755, true);
        }
        
        // On vérifie si c'est un lien symbolique ou un dossier réel Maya.
        if (!is_link(public_path('storage'))) {
            copy(storage_path('app/public/products/' . $fileName), $publicStoragePath . '/' . $fileName);
        }
        
        // 3. Mise à jour DB avec le chemin relatif standard Maya.
        $product->update(['image_path' => 'products/' . $fileName]);
    }
}
