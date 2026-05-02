<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;

class PublicBlogController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Article::where('status', 'published')->with('product');
            
            if ($request->has('category')) {
                $query->whereRaw('LOWER(category) = ?', [strtolower($request->category)]);
            }

            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            // Fallback ultra-safe si avec('product') plante
            return Article::where('status', 'published')->orderBy('created_at', 'desc')->get();
        }
    }

    public function show($slug)
    {
        return Article::where('slug', $slug)->where('status', 'published')->with('product')->firstOrFail();
    }
}
