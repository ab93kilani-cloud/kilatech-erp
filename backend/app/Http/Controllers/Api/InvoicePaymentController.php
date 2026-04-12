<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InvoicePaymentController extends Controller
{
    public function index()
    {
        return response()->json(InvoicePayment::with('invoice.order.client')->get());
    }

    /**
     * Enregistrer un nouveau paiement et mettre à jour le solde
     */
    public function store(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $invoice = Invoice::findOrFail($request->invoice_id);

        if ($invoice->status === 'paid') {
            return response()->json(['message' => 'Cette facture est déjà totalement payée.'], 400);
        }

        if ($request->amount > $invoice->balance_due) {
            return response()->json(['message' => 'Le montant du paiement ('. $request->amount .') est supérieur au solde restant dû ('. $invoice->balance_due .').'], 400);
        }

        DB::beginTransaction();
        try {
            $payment = InvoicePayment::create($request->all());

            $newBalance = round($invoice->balance_due - $request->amount, 2);
            $status = $newBalance <= 0.001 ? 'paid' : 'partially_paid';

            $invoice->update([
                'balance_due' => $newBalance,
                'status' => $status,
            ]);

            // Si la facture est totalement payée, on l'archive automatiquement
            if ($newBalance <= 0) {
                $invoice->delete();
            }

            DB::commit();
            
            return response()->json($payment->load('invoice'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors du paiement', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(InvoicePayment $invoicePayment)
    {
        return response()->json($invoicePayment->load('invoice'));
    }
}
