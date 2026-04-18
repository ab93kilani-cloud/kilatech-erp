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
        $query = PurchaseOrder::with(['supplier', 'creator', 'lines.variant', 'receipts', 'payments']);
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
            $poDate = $request->po_date ? \Carbon\Carbon::parse($request->po_date) : \Carbon\Carbon::now();
            $prefix = 'PO-' . $poDate->format('Y');
            
            $lastPO = PurchaseOrder::withTrashed()
                ->where('po_number', 'like', $prefix . '-%')
                ->get()
                ->sortByDesc(function($p) {
                    $parts = explode('-', $p->po_number);
                    return (int) end($parts);
                })
                ->first();
            if ($lastPO) {
                $parts = explode('-', $lastPO->po_number);
                $nextNum = ((int) end($parts)) + 1;
            } else {
                $nextNum = 1;
            }
            $poNumber = $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $po = PurchaseOrder::create([
                'supplier_id' => $request->supplier_id,
                'created_by' => $request->user()->id,
                'po_number' => $poNumber,
                'po_date' => $poDate->toDateString(),
                'status' => 'draft',
                'payment_method' => $request->payment_method ?? 'Non défini',
                'payment_conditions' => $request->payment_conditions ?? 'A reception de facture',
                'expected_delivery_date' => $request->expected_delivery_date ?? null,
                'due_date' => $request->due_date ?? null,
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

    public function show($id)
    {
        $purchaseOrder = PurchaseOrder::withTrashed()->findOrFail($id);
        return response()->json($purchaseOrder->load([
            'supplier', 
            'creator', 
            'lines.variant.product',
            'receipts.receiver',
            'receipts.lines.poLine.variant',
            'receipts.lines.stockMovements.unit.lot',
            'payments'
        ]));
    }

    public function update(Request $request, $id)
    {
        $purchaseOrder = PurchaseOrder::withTrashed()->findOrFail($id);
        
        $isArchived = in_array($purchaseOrder->status, ['received', 'completed']);

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
                'po_date' => $request->po_date ?? $purchaseOrder->po_date,
                'payment_method' => $request->payment_method ?? 'Non défini',
                'payment_conditions' => $request->payment_conditions ?? 'A reception de facture',
                'expected_delivery_date' => $request->expected_delivery_date ?? null,
                'due_date' => $request->due_date ?? $purchaseOrder->due_date,
            ]);

            if ($isArchived) {
                // REGULARIZATION MODE: Update prices AND quantities + Sync Stock
                foreach ($request->lines as $lineData) {
                    $poLine = $purchaseOrder->lines()
                        ->where('variant_id', $lineData['variant_id'])
                        ->first();
                    
                    if ($poLine) {
                        $oldQty = $poLine->ordered_quantity;
                        $newQty = $lineData['ordered_quantity'];
                        $diff = $newQty - $oldQty;

                        // Update PO line
                        $poLine->update([
                            'unit_price' => $lineData['unit_price'],
                            'ordered_quantity' => $newQty,
                            'received_quantity' => $newQty // Assume full reception for archived
                        ]);

                        if ($diff !== 0) {
                            // Sync Physical Stock
                            // Find all receipt lines for this PO line
                            $receiptLineIds = \App\Models\ReceiptLine::where('po_line_id', $poLine->id)->pluck('id');
                            
                            if ($diff < 0) {
                                // REDUCTION: Remove available stock units
                                $unitsToRemove = \App\Models\StockUnit::whereIn('id', function($query) use ($receiptLineIds) {
                                        $query->select('unit_id')
                                            ->from('stock_movements')
                                            ->where('reference_doc_type', \App\Models\ReceiptLine::class)
                                            ->whereIn('reference_doc_id', $receiptLineIds);
                                    })
                                    ->where('status', 'disponible')
                                    ->limit(abs($diff))
                                    ->get();

                                foreach ($unitsToRemove as $unit) {
                                    $unit->movements()->delete();
                                    $unit->forceDelete();
                                }
                            } else {
                                // ADDITION: Create new stock units
                                // Find the last lot created for this line
                                $lastReceiptLine = \App\Models\ReceiptLine::whereIn('id', $receiptLineIds)
                                    ->where('status', 'approved')
                                    ->latest()
                                    ->first();
                                
                                $lot = null;
                                if ($lastReceiptLine) {
                                    $movement = \App\Models\StockMovement::where('reference_doc_type', \App\Models\ReceiptLine::class)
                                        ->where('reference_doc_id', $lastReceiptLine->id)
                                        ->with('unit.lot')
                                        ->first();
                                    $lot = $movement?->unit?->lot;
                                }

                                if (!$lot) {
                                    // Fallback: create a system lot if none found
                                    $lot = \App\Models\Lot::create([
                                        'variant_id' => $poLine->variant_id,
                                        'lot_number' => 'REGUL-' . now()->format('Ymd'),
                                        'actual_purchase_price' => $poLine->unit_price
                                    ]);
                                }

                                for ($i = 0; $i < $diff; $i++) {
                                    $unit = \App\Models\StockUnit::create([
                                        'lot_id' => $lot->id,
                                        'status' => 'disponible'
                                    ]);

                                    \App\Models\StockMovement::create([
                                        'unit_id' => $unit->id,
                                        'user_id' => $request->user()->id,
                                        'type' => 'in',
                                        'to_status' => 'disponible',
                                        'reference_doc_type' => \App\Models\PurchaseOrder::class,
                                        'reference_doc_id' => $purchaseOrder->id
                                    ]);
                                }
                            }
                        }
                    }
                }
            } else {
                // DRAFT UPDATE: Clear old lines and recreate (Full flexibility)
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
            'document_date' => $purchaseOrder->po_date ?? $purchaseOrder->created_at,
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

    public function destroy($id)
    {
        // On récupère le PO même s'il est déjà archivé (soft-deleted)
        $purchaseOrder = PurchaseOrder::withTrashed()->findOrFail($id);

        // On autorise la suppression en Brouillon OU si Commandé sans aucune réception
        if ($purchaseOrder->status === 'received' || $purchaseOrder->status === 'completed') {
            return response()->json(['message' => 'Ce bon de commande ne peut plus être supprimé car il est déjà complété ou reçu.'], 400);
        }

        if ($purchaseOrder->receipts()->count() > 0) {
            return response()->json(['message' => 'Suppression impossible : des réceptions (même partielles) ont déjà été enregistrées pour ce BC. Supprimez d\'abord les réceptions liées.'], 400);
        }

        DB::beginTransaction();
        try {
            // Suppression DÉFINITIVE des lignes pour éviter le conflit SQL
            $purchaseOrder->lines()->forceDelete();
            
            // Suppression DÉFINITIVE du bon de commande (Force delete)
            $purchaseOrder->forceDelete();
            
            DB::commit();
            return response()->json(['message' => 'Bon de commande supprimé définitivement.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur technique lors de la suppression : ' . $e->getMessage()], 500);
        }
    }
}
