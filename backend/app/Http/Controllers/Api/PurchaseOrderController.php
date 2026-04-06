<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PoLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'creator', 'lines.variant']);
        if ($request->has('archived')) {
            $query->onlyTrashed();
        }
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|exists:product_variants,id',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.ordered_quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Generates PO number like PO-2602-0001
            $nextId = (PurchaseOrder::withTrashed()->max('id') ?? 0) + 1;
            $poNumber = 'PO-' . date('ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'supplier_id' => $request->supplier_id,
                'created_by' => $request->user()->id,
                'po_number' => $poNumber,
                'status' => 'draft',
                'payment_method' => $request->payment_method ?? 'Non défini',
                'payment_conditions' => $request->payment_conditions ?? 'A reception de facture',
                'expected_delivery_date' => $request->expected_delivery_date ?? null,
            ]);

            foreach ($request->lines as $line) {
                PoLine::create([
                    'purchase_order_id' => $po->id,
                    'variant_id' => $line['variant_id'],
                    'unit_price' => $line['unit_price'],
                    'ordered_quantity' => $line['ordered_quantity'],
                    'received_quantity' => 0,
                ]);
            }

            DB::commit();
            return response()->json($po->load('lines'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création du PO', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json($purchaseOrder->load([
            'supplier', 
            'creator', 
            'lines.variant.product',
            'receipts.receiver',
            'receipts.lines.poLine.variant',
            'receipts.lines.stockMovements.unit.lot'
        ]));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Seul un PO en brouillon peut être modifié'], 400);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|exists:product_variants,id',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
            'lines.*.ordered_quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $purchaseOrder->update([
                'supplier_id' => $request->supplier_id,
                'payment_method' => $request->payment_method ?? 'Non défini',
                'payment_conditions' => $request->payment_conditions ?? 'A reception de facture',
                'expected_delivery_date' => $request->expected_delivery_date ?? null,
            ]);

            // Clear old lines and recreate
            $purchaseOrder->lines()->delete();

            foreach ($request->lines as $line) {
                PoLine::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'variant_id' => $line['variant_id'],
                    'unit_price' => $line['unit_price'],
                    'ordered_quantity' => $line['ordered_quantity'],
                    'received_quantity' => 0,
                ]);
            }

            DB::commit();
            return response()->json($purchaseOrder->load('lines'), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la modification du PO', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Valider la commande
     */
    public function confirm(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Seul un PO en brouillon peut être confirmé'], 400);
        }

        $purchaseOrder->update(['status' => 'ordered']);
        return response()->json($purchaseOrder);
    }

    /**
     * Archive manuellement la commande (si livraison incomplète mais finie)
     */
    public function archive(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === 'draft') {
            return response()->json(['message' => 'Impossible d\'archiver un brouillon non confirmé'], 400);
        }

        $purchaseOrder->update(['status' => 'completed']);
        $purchaseOrder->delete();
        return response()->json($purchaseOrder);
    }

    public function generatePDF($id)
    {
        $purchaseOrder = PurchaseOrder::withTrashed()->with(['supplier', 'creator', 'lines.variant.product'])->findOrFail($id);

        $totalHT = 0;
        $totalTTC = 0;
        $totalTVA = 0;
        $tvaBreakdown = [];

        $items = $purchaseOrder->lines->map(function ($line) use (&$totalHT, &$totalTTC, &$totalTVA, &$tvaBreakdown) {
            $ht = (float)($line->unit_price ?? 0) * (int)($line->ordered_quantity ?? 1);
            $tvaRate = (float)($line->variant?->tva_rate ?? 20);
            $ttc = $ht * (1 + ($tvaRate / 100));
            $tva = $ttc - $ht;

            $totalHT += $ht;
            $totalTTC += $ttc;
            $totalTVA += $tva;

            // Breakdown par taux
            $rateKey = (string)$tvaRate;
            if (!isset($tvaBreakdown[$rateKey])) {
                $tvaBreakdown[$rateKey] = ['ht' => 0, 'tva' => 0];
            }
            $tvaBreakdown[$rateKey]['ht'] += $ht;
            $tvaBreakdown[$rateKey]['tva'] += $tva;

            return [
                'reference' => $line->variant?->sku ?? '-',
                'name' => ($line->variant?->product?->name ?? 'Produit') . ($line->variant?->product?->brand ? ' - ' . $line->variant->product->brand : '') . ($line->variant?->name_variation ? ' - ' . $line->variant->name_variation : ''),
                'description' => '',
                'quantity' => $line->ordered_quantity,
                'unit' => $line->variant?->product?->unit ?? 'U',
                'unit_price_ht' => (float)($line->unit_price ?? 0),
                'unit_price_ttc' => (float)($line->unit_price ?? 0) * (1 + ($tvaRate / 100)),
                'total_ht' => $ht,
                'total_ttc' => $ttc,
                'tva' => $tvaRate,
                'discount' => '0%',
            ];
        });

        $data = [
            'document_type' => 'BON DE COMMANDE',
            'document_number' => $purchaseOrder->po_number,
            'document_date' => $purchaseOrder->created_at,
            'client_name' => $purchaseOrder->supplier ? ($purchaseOrder->supplier->name ?? 'Fournisseur Inconnu') : 'Fournisseur Non Défini',
            'client_ice' => $purchaseOrder->supplier ? ($purchaseOrder->supplier->ice ?? '-') : '-',
            'client_address' => $purchaseOrder->supplier ? ($purchaseOrder->supplier->address ?? '-') : '-',
            'client_city' => $purchaseOrder->supplier ? ($purchaseOrder->supplier->city ?? '-') : '-',
            'items' => $items,
            'total_ht' => $totalHT,
            'total_ttc' => $totalTTC,
            'total_tva' => $totalTVA,
            'tva_breakdown' => $tvaBreakdown,
            'amount_in_words' => \App\Helpers\NumberToWords::convert($totalTTC),
            'title' => 'BC_' . $purchaseOrder->po_number,
            'payment_method' => $purchaseOrder->payment_method ?? 'Virement',
            'validity_date' => $purchaseOrder->payment_conditions ?? 'À réception',
            'delivery_at' => $purchaseOrder->expected_delivery_date ?? null,
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.purchase_order', $data);
        return $pdf->download('Bon_de_Commande_' . $purchaseOrder->po_number . '.pdf');
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        // On autorise la suppression en Brouillon OU si Commandé sans aucune réception
        if ($purchaseOrder->status !== 'draft' && $purchaseOrder->status !== 'ordered') {
            return response()->json(['message' => 'Ce bon de commande ne peut plus être supprimé (déjà en cours de réception)'], 400);
        }

        if ($purchaseOrder->receipts()->count() > 0) {
            return response()->json(['message' => 'Suppression impossible : des réceptions ont déjà été enregistrées pour ce BC'], 400);
        }

        DB::beginTransaction();
        try {
            $purchaseOrder->lines()->delete();
            $purchaseOrder->delete();
            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la suppression du PO'], 500);
        }
    }
}
