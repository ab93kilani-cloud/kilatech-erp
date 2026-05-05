<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::orderBy('name', 'asc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:categories,name|max:255',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $request->validate([
            'name' => 'required|string|unique:categories,name,' . $category->id . '|max:255',
        ]);

        $oldName = $category->name;
        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        // Update all products linked to this category string
        \App\Models\Product::where('category', $oldName)->update(['category' => $request->name]);

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $productCount = \App\Models\Product::where('category', $category->name)->count();
        if ($productCount > 0) {
            return response()->json(['message' => "Impossible de supprimer cette catégorie car elle est liée à $productCount produits."], 422);
        }

        $category->delete();
        return response()->json(['message' => 'Catégorie supprimée avec succès.']);
    }
}
