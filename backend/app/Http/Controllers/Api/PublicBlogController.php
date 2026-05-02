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
            $query = \App\Models\Article::where('status', 'published');
            
            if ($request->has('category')) {
                $query->where('category', strtolower($request->category));
            }

            return $query->orderBy('created_at', 'desc')->get();
        } catch (\Exception $e) {
            return \App\Models\Article::where('status', 'published')->get();
        }
    }

    public function show($slug)
    {
        return Article::where('slug', $slug)->where('status', 'published')->with('product')->firstOrFail();
    }
}
