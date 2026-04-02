<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductSupplier;
use Illuminate\Http\Request;

class ProductSupplierController extends Controller
{
    public function index()
    {
        return response()->json(ProductSupplier::with(['variant.product', 'supplier'])->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'supplier_ref' => 'nullable|string|max:255',
            'purchase_price' => 'required|numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
            'price_validity_date' => 'nullable|date',
        ]);

        // Prevent exact duplicates
        $exists = ProductSupplier::where('variant_id', $request->variant_id)
                                 ->where('supplier_id', $request->supplier_id)
                                 ->first();
        if ($exists) {
            return response()->json(['message' => 'Ce fournisseur est déjà lié à cette variante.'], 409);
        }

        $productSupplier = ProductSupplier::create($request->all());

        return response()->json($productSupplier->load(['variant', 'supplier']), 201);
    }

    public function show(ProductSupplier $productSupplier)
    {
        return response()->json($productSupplier->load(['variant.product', 'supplier']));
    }

    public function update(Request $request, ProductSupplier $productSupplier)
    {
        $request->validate([
            'supplier_ref' => 'nullable|string|max:255',
            'purchase_price' => 'numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0',
            'price_validity_date' => 'nullable|date',
        ]);

        $productSupplier->update($request->all());

        return response()->json($productSupplier->load(['variant', 'supplier']));
    }

    public function destroy(ProductSupplier $productSupplier)
    {
        $productSupplier->delete();
        return response()->json(null, 204);
    }
}
