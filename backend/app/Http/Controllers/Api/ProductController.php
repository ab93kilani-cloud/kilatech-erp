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
        return response()->json(Product::with(['variants.suppliers', 'supplier'])->get());
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
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
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
                $variant = $product->variants()->create($v);
                
                // Maya: Enregistrement des fournisseurs alternatifs
                if (!empty($v['suppliers']) && is_array($v['suppliers'])) {
                    foreach ($v['suppliers'] as $ps) {
                        if (!empty($ps['supplier_id'])) {
                            \App\Models\ProductSupplier::create([
                                'variant_id' => $variant->id,
                                'supplier_id' => $ps['supplier_id'],
                                'supplier_ref' => !empty($ps['supplier_ref']) ? $ps['supplier_ref'] : null,
                                'purchase_price' => (isset($ps['purchase_price']) && $ps['purchase_price'] !== '') ? $ps['purchase_price'] : ($variant->cost_price ?? 0),
                            ]);
                        }
                    }
                }
            }

            if ($request->hasFile('image')) {
                $this->processAndStoreImage($request->file('image'), $product, true);
            }

            if ($request->hasFile('images')) {
                $images = [];
                foreach ($request->file('images') as $file) {
                    $images[] = $this->processAndStoreImage($file, $product, false);
                }
                $product->update(['images' => $images]);
            }

            DB::commit();
            return response()->json($product->load('variants'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show($idOrSlug)
    {
        $product = Product::with(['variants.suppliers.supplier', 'supplier'])
            ->where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->first();
        
        if (!$product) {
            // Handle soft-deleted
            $product = Product::onlyTrashed()->with(['variants.suppliers.supplier', 'supplier'])
                ->where('id', $idOrSlug)
                ->orWhere('slug', $idOrSlug)
                ->first();
                
            if ($product) {
                return response()->json(['message' => 'Produit archivé (corbeille)'], 404);
            }
            return response()->json(['message' => 'Produit non trouvé : ' . $idOrSlug], 404);
        }

        // Add related products (same category, excluding current)
        // Explicit selection for performance and safety
        $related = Product::where('category', $product->category)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->select(['id', 'name', 'slug', 'image_path', 'brand', 'public_price', 'category'])
            ->get();
            
        $product->setAttribute('related', $related);

        return response()->json($product);
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
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'images_to_delete' => 'nullable|array',
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
            $product->update($request->except(['image', 'images', 'variants', 'deleted_variant_ids', 'images_to_delete']));

            if (!empty($deletedIds)) {
                $product->variants()->whereIn('id', $deletedIds)->delete();
            }

            foreach ($variantsData as $v) {
                if (!empty($v['id'])) {
                    $variant = \App\Models\ProductVariant::findOrFail($v['id']);
                    $variant->update([
                        'sku' => trim($v['sku']),
                        'name_variation' => $v['name_variation'] ?? null,
                        'cost_price' => $v['cost_price'] ?? null,
                        'price_ht' => $v['price_ht'] ?? null,
                        'tva_rate' => collect($v)->has('tva_rate') ? $v['tva_rate'] : 20,
                        'public_price' => $v['public_price'] ?? null,
                        'margin_percent' => $v['margin_percent'] ?? 0,
                    ]);
                } else {
                    $variant = $product->variants()->create($v);
                }

                // Maya: Sync des fournisseurs par variante
                if (isset($v['suppliers']) && is_array($v['suppliers'])) {
                    $variant->suppliers()->delete(); // On repart à neuf pour cette variante
                    foreach ($v['suppliers'] as $ps) {
                        if (!empty($ps['supplier_id'])) {
                            \App\Models\ProductSupplier::create([
                                'variant_id' => $variant->id,
                                'supplier_id' => $ps['supplier_id'],
                                'supplier_ref' => !empty($ps['supplier_ref']) ? $ps['supplier_ref'] : null,
                                'purchase_price' => (isset($ps['purchase_price']) && $ps['purchase_price'] !== '') ? $ps['purchase_price'] : ($variant->cost_price ?? 0),
                            ]);
                        }
                    }
                }
            }

            $currentImages = $product->images ?? [];
            if ($request->has('images_to_delete')) {
                $toDelete = $request->input('images_to_delete');
                $currentImages = array_filter($currentImages, function ($img) use ($toDelete) {
                    return !in_array($img, $toDelete);
                });
            }

            if ($request->hasFile('image')) {
                $this->processAndStoreImage($request->file('image'), $product, true);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $file) {
                    $currentImages[] = $this->processAndStoreImage($file, $product, false);
                }
            }
            $product->update(['images' => array_values($currentImages)]);

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

    public function generateMarketing(Product $product)
    {
        // We no longer use n8n for this. Mode is now 100% manual for better quality.
        $product->update(['seo_status' => 'optimized']);
        
        return response()->json([
            'message' => 'Mode manuel activé. Vous pouvez maintenant éditer la fiche avec les outils premium.',
            'product' => $product
        ]);
    }

    /**
     * Direct GSC Sync (Replacing n8n bridge)
     */
    public function syncGsc($id, \App\Services\GoogleSearchConsoleService $gsc)
    {
        $product = Product::findOrFail($id);
        
        if (!$product->slug) {
            return response()->json(['success' => false, 'message' => 'Le produit doit avoir un slug pour être synchronisé.'], 422);
        }

        $path = 'produit/' . $product->slug;
        $stats = $gsc->getUrlStats($path);

        if ($stats) {
            $product->update([
                'gsc_position' => $stats['position'],
                'gsc_ctr' => $stats['ctr'],
                'gsc_impressions' => $stats['impressions'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Synchronisation GSC réussie !',
                'data' => $stats
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Impossible de récupérer les données GSC. Vérifiez la configuration de l\'API.'
        ], 500);
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
    private function processAndStoreImage($file, Product $product, $isMain = true)
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::slug($product->name) . '-' . uniqid() . '.' . $extension;
        
        $path = $file->storeAs('products', $fileName, 'public');
        
        $publicStoragePath = public_path('storage/products');
        if (!is_dir($publicStoragePath)) {
            mkdir($publicStoragePath, 0755, true);
        }
        
        if (!is_link(public_path('storage'))) {
            copy(storage_path('app/public/products/' . $fileName), $publicStoragePath . '/' . $fileName);
        }
        
        if ($isMain) {
            $product->update(['image_path' => 'products/' . $fileName]);
        }
        
        return 'products/' . $fileName;
    }
}
