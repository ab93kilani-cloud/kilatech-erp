<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierPaymentController extends Controller
{
    public function index()
    {
        return response()->json(
            SupplierPayment::with(['purchaseOrder.supplier'])
                ->latest('payment_date')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
            'maturity_date' => 'nullable|date',
        ]);

        $po = PurchaseOrder::withTrashed()->findOrFail($request->purchase_order_id);

        DB::beginTransaction();
        try {
            $payment = SupplierPayment::create($request->all());

            // Optional: Update PO status if fully paid logic is needed here
            // For now we just record the payment as requested.

            DB::commit();
            
            return response()->json($payment->load('purchaseOrder'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de l\'enregistrement du règlement fournisseur.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(SupplierPayment $supplierPayment)
    {
        return response()->json($supplierPayment->load('purchaseOrder.supplier'));
    }
}
