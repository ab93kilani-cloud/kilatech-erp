<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductVariantController extends Controller
{
    public function index()
    {
        return response()->json(ProductVariant::with(['product', 'suppliers'])->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'sku' => ['required', 'string', Rule::unique('product_variants', 'sku')->whereNull('deleted_at')],
            'name_variation' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'price_ht' => 'nullable|numeric|min:0',
            'tva_rate' => 'nullable|numeric|min:0|max:100',
            'margin_percent' => 'nullable|numeric|min:-100',
            'public_price' => 'nullable|numeric|min:0',
        ]);

        $variant = ProductVariant::create($request->all());

        return response()->json($variant->load('product'), 201);
    }

    public function show($id)
    {
        $productVariant = ProductVariant::findOrFail($id);
        return response()->json($productVariant->load(['product', 'suppliers']));
    }

    public function update(Request $request, $id)
    {
        $productVariant = ProductVariant::findOrFail($id);

        $request->validate([
            'product_id' => 'exists:products,id',
            'sku' => ['string', Rule::unique('product_variants', 'sku')->ignore($productVariant->id)->whereNull('deleted_at')],
            'name_variation' => 'nullable|string|max:255',
            'cost_price' => 'nullable|numeric|min:0',
            'price_ht' => 'nullable|numeric|min:0',
            'tva_rate' => 'nullable|numeric|min:0|max:100',
            'margin_percent' => 'nullable|numeric|min:-100',
            'public_price' => 'nullable|numeric|min:0',
        ]);

        $productVariant->update($request->all());

        return response()->json($productVariant->load('product'));
    }

    public function destroy($id)
    {
        $productVariant = ProductVariant::findOrFail($id);
        $productVariant->delete();
        return response()->json(null, 204);
    }
}
