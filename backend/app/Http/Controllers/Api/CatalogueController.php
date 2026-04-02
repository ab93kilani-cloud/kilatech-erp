<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatalogueController extends Controller
{
    public function index()
    {
        return Catalogue::where('is_active', true)->orderBy('created_at', 'desc')->get();
    }

    public function managerIndex()
    {
        return Catalogue::orderBy('created_at', 'desc')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:pdf|max:10240', // 10MB
            'category' => 'nullable|string|max:255',
        ]);

        $path = $request->file('file')->store('catalogues', 'public');

        $catalogue = Catalogue::create([
            'title' => $request->title,
            'file_path' => $path,
            'category' => $request->category,
        ]);

        return response()->json($catalogue, 201);
    }

    public function destroy(Catalogue $catalogue)
    {
        Storage::disk('public')->delete($catalogue->file_path);
        $catalogue->delete();
        return response()->json(null, 204);
    }

    public function toggleStatus(Catalogue $catalogue)
    {
        $catalogue->update(['is_active' => !$catalogue->is_active]);
        return response()->json($catalogue);
    }
}
