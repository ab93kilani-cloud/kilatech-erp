<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index()
    {
        return response()->json(Supplier::orderBy('name', 'asc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'ice' => 'nullable|string|max:50',
            'rc' => 'nullable|string|max:50',
            'if' => 'nullable|string|max:50',
            'patente' => 'nullable|string|max:50',
        ]);

        $supplier = Supplier::create($request->all());

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json($supplier->load('productSuppliers.variant'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $request->validate([
            'name' => 'string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
        ]);

        $supplier->update($request->all());

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();
        return response()->json(null, 204);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt,xlsx,xls|max:2048'
        ]);

        \Maatwebsite\Excel\Facades\Excel::import(new \App\Imports\SupplierImport, $request->file('file'));

        return response()->json(['message' => 'Importation des fournisseurs réussie.'], 200);
    }
}
