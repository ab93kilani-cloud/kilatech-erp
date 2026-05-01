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
        if ($request->input('api_token') !== 'KILA-SEO-PROD-2026-X9') {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->store($request);
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
}
