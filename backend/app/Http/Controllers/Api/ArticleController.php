<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Product;
use App\Services\MarketingAiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    public function publicList(Request $request)
    {
        $query = Article::where('status', 'published')->with('product');
        
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function publicShow($slug)
    {
        $article = Article::where('slug', $slug)->where('status', 'published')->with('product')->firstOrFail();
        return $article;
    }

    public function index()
    {
        return Article::with('product')->orderBy('created_at', 'desc')->get();
    }

    protected function generateUniqueSlug($title, $id = null)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (Article::where('slug', $slug)->where('id', '!=', $id)->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'product_id' => 'nullable|exists:products,id',
            'status' => 'required|in:draft,published',
            'image' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'seo_alt' => 'nullable|string',
            'category' => 'sometimes|required|in:blog,news',
        ]);

        $validated['slug'] = $this->generateUniqueSlug($validated['title']);

        $article = Article::create($validated);
        return response()->json($article, 201);
    }

    /**
     * V2 Method for n8n automated posting.
     */
    public function storeV2(Request $request)
    {
        // 1. PRIORITÉ ABSOLUE : Si c'est une demande de produits Elite, on répond tout de suite
        if ($request->input('action') === 'get_elite') {
            return $this->eliteProducts();
        }

        // 2. Sinon, on vérifie le token pour la publication
        if ($request->input('api_token') !== 'KILA-SEO-PROD-2026-X9') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // 3. SOLUTION PARFAITE : Liaison par Référence
        $reference = $request->input('product_reference');
        
        // Nettoyage automatique du titre (enlève les '=' de n8n)
        $title = ltrim($request->input('h1_title') ?: $request->input('title'), '= ');
        
        $articleData = [
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title) . '-' . time(), // Slug unique avec timestamp
            'content' => $request->input('html_content') ?: $request->input('content'),
            'status' => 'published',
            'category' => $request->input('category') ?: 'blog',
            'meta_title' => $request->input('meta_title'),
            'meta_description' => $request->input('meta_description'),
            'image' => $request->input('image'),
            'seo_alt' => $request->input('seo_alt'),
        ];
        // Liaison produit via référence
        if ($reference) {
            $product = \App\Models\Product::where('reference', $reference)->first();
            if ($product) {
                $articleData['product_id'] = $product->id;
            }
        }

        // SÉCURITÉ : Si aucun produit n'est trouvé, on prend le tout premier de la base
        if (!isset($articleData['product_id'])) {
            $firstProduct = \App\Models\Product::orderBy('id', 'asc')->first();
            $articleData['product_id'] = $firstProduct ? $firstProduct->id : null;
        }

        // Création de l'article avec gestion d'erreur
        try {
            $article = \App\Models\Article::create($articleData);
            return response()->json($article);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la création de l\'article',
                'details' => $e->getMessage(),
                'received_data' => $articleData
            ], 500);
        }
    }

    public function show(Article $article)
    {
        return $article->load('product');
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|string',
            'product_id' => 'nullable|exists:products,id',
            'status' => 'sometimes|required|in:draft,published',
            'image' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'category' => 'sometimes|required|in:blog,news',
        ]);

        if (isset($validated['title'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['title'], $article->id);
        }

        $article->update($validated);
        return response()->json($article);
    }

    public function destroy(Article $article)
    {
        $article->delete();
        return response()->json(null, 204);
    }

    public function toggleStatus(Article $article)
    {
        $article->status = $article->status === 'published' ? 'draft' : 'published';
        $article->save();
        return response()->json($article);
    }

    public function generate(Request $request, MarketingAiService $marketingAi)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $product = Product::find($request->product_id);
        $articleData = $marketingAi->generateArticleForProduct($product);

        return response()->json($articleData);
    }

    public function generateNews(Request $request, MarketingAiService $marketingAi)
    {
        $request->validate([
            'notes' => 'required|string|min:10',
        ]);

        $articleData = $marketingAi->generateNewsArticle($request->notes);

        return response()->json($articleData);
    }

    public function eliteProducts()
    {
        $products = \App\Models\Product::where('status', 'active')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get(['id', 'name', 'slug', 'reference']);

        return response()->json($products);
    }
}
