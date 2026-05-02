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
            // Test minimal sans category ni relationships
            return \App\Models\Article::select('id', 'title', 'slug', 'content')->get();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($slug)
    {
        return Article::where('slug', $slug)->where('status', 'published')->with('product')->firstOrFail();
    }
}
