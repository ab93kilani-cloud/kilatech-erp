<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Receipt;
use App\Models\ReceiptLine;
use App\Models\PoLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReceiptController extends Controller
{
    public function index()
    {
        return response()->json(Receipt::with([
            'purchaseOrder' => function($q) { $q->withTrashed()->with('supplier'); }, 
            'receiver', 
            'lines.poLine.variant.product', 
            'lines.stockMovements.unit.lot'
        ])->get());
    }

    /**
     * Reçoit une commande (partiellement ou totalement)
     */
    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'receipt_date' => 'required|date',
            'notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.po_line_id' => 'required|exists:po_lines,id',
            'lines.*.received_quantity' => 'required|integer|min:1',
            'lines.*.actual_purchase_price' => 'nullable|numeric|min:0',
            'lines.*.lot_number' => 'nullable|string|max:50',
            'lines.*.expiry_date' => 'nullable|date',
        ]);

        $po = PurchaseOrder::findOrFail($request->purchase_order_id);
        
        if ($po->status !== 'ordered' && $po->status !== 'partially_received') {
            return response()->json(['message' => 'Impossible de recevoir une commande au statut : ' . $po->status], 400);
        }

        DB::beginTransaction();
        try {
            $prefix = 'REC-' . date('ym');
            $lastRec = Receipt::withTrashed()
                ->where('receipt_number', 'like', $prefix . '-%')
                ->get()
                ->sortByDesc(function($r) {
                    $parts = explode('-', $r->receipt_number);
                    return (int) end($parts);
                })
                ->first();
            if ($lastRec) {
                $parts = explode('-', $lastRec->receipt_number);
                $nextNum = ((int) end($parts)) + 1;
            } else {
                $nextNum = 1;
            }
            $receiptNumber = $prefix . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $receipt = Receipt::create([
                'purchase_order_id' => $po->id,
                'received_by' => $request->user()->id,
                'receipt_number' => $receiptNumber,
                'receipt_date' => $request->receipt_date,
                'notes' => $request->notes,
            ]);

            $allLinesReceived = true; // Check if the PO is fully received

            foreach ($request->lines as $lineData) {
                $poLine = PoLine::where('id', $lineData['po_line_id'])
                                ->where('purchase_order_id', $po->id)
                                ->lockForUpdate() // Pessimistic locking
                                ->firstOrFail();

                // Validation to avoid receiving more than ordered (optional depending on business rules, but safer)
                $newTotalReceived = $poLine->received_quantity + $lineData['received_quantity'];
                if ($newTotalReceived > $poLine->ordered_quantity) {
                    throw new \Exception("Quantité reçue supérieure à la quantité commandée pour la ligne " . $poLine->id);
                }

                // Create Receipt Line
                ReceiptLine::create([
                    'receipt_id' => $receipt->id,
                    'po_line_id' => $poLine->id,
                    'received_quantity' => $lineData['received_quantity'],
                    'actual_purchase_price' => $lineData['actual_purchase_price'] ?? $poLine->unit_price,
                    'lot_number' => $lineData['lot_number'] ?? null,
                    'expiry_date' => $lineData['expiry_date'] ?? null,
                    'status' => 'pending_qa', // Waiting for QA and Lot creation
                ]);

                // Update PO Line
                $poLine->update(['received_quantity' => $newTotalReceived]);

                if ($newTotalReceived < $poLine->ordered_quantity) {
                    $allLinesReceived = false;
                }
            }

            // Check if there are other PO Lines not included in this receipt that are still pending
            $pendingLines = PoLine::where('purchase_order_id', $po->id)
                                    ->whereColumn('received_quantity', '<', 'ordered_quantity')
                                    ->exists();
            
            if ($pendingLines || !$allLinesReceived) {
                $po->update(['status' => 'partially_received']);
            } else {
                $po->update(['status' => 'completed']);
                $po->delete(); // Archivage automatique si complété
            }

            DB::commit();
            return response()->json($receipt->load('lines.poLine'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création du bon de réception', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Receipt $receipt)
    {
        return response()->json($receipt->load(['purchaseOrder', 'receiver', 'lines.poLine.variant']));
    }

    /**
     * Annule un bon de livraison et restaure les quantités sur le BC
     */
    public function destroy(Receipt $receipt)
    {
        // Sécurité : On ne peut annuler que si aucune ligne n'a encore passé le QA
        foreach ($receipt->lines as $line) {
            if ($line->status !== 'pending_qa') {
                return response()->json(['message' => 'Impossible d\'annuler ce BL car certains articles ont déjà été validés par la QA.'], 400);
            }
        }

        DB::beginTransaction();
        try {
            $poId = $receipt->purchase_order_id;
            
            foreach ($receipt->lines as $line) {
                $poLine = $line->poLine;
                if ($poLine) {
                    $poLine->received_quantity = max(0, $poLine->received_quantity - $line->received_quantity);
                    $poLine->save();
                }
                $line->delete();
            }

            $receipt->delete();

            // Recalculer le statut du Bon de Commande
            $po = PurchaseOrder::find($poId);
            if ($po) {
                $anyReceived = $po->lines()->where('received_quantity', '>', 0)->exists();
                if (!$anyReceived) {
                    // Si plus aucune ligne n'a de réception, on repasse en 'ordered' (ou 'sent' selon le flux)
                    $po->status = 'ordered'; 
                } else {
                    $po->status = 'partially_received';
                }
                $po->save();
            }

            DB::commit();
            return response()->json(['message' => 'Bon de livraison annulé avec succès. Les quantités ont été réinitialisées sur le Bon de Commande.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de l\'annulation du BL', 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadInvoice(Request $request, $id)
    {
        $request->validate([
            'invoice_file' => 'required|file|mimes:pdf'
        ]);

        $receipt = Receipt::findOrFail($id);
        $path = $request->file('invoice_file')->store('purchase_invoices', 'public');
        
        $receipt->update([
            'invoice_path' => $path,
            'invoice_status' => 'received'
        ]);

        return response()->json(['message' => 'Facture téléchargée avec succès.', 'receipt' => $receipt]);
    }

    public function revertToPO($id)
    {
        $receipt = Receipt::findOrFail($id);

        foreach ($receipt->lines as $line) {
            if ($line->status === 'completed') {
                return response()->json(['message' => 'Impossible de retourner au BC : certains articles ont déjà été validés physiquement en QA.'], 400);
            }
        }

        DB::beginTransaction();
        try {
            $poId = $receipt->purchase_order_id;
            
            // Re-fetch withtrashed to restore
            $po = PurchaseOrder::withTrashed()->findOrFail($poId);
            $po->restore();
            $po->update(['status' => 'ordered']);

            foreach ($receipt->lines as $line) {
                $poLine = $line->poLine;
                if ($poLine) {
                    $poLine->received_quantity = max(0, $poLine->received_quantity - $line->received_quantity);
                    $poLine->save();
                }
            }

            $receipt->lines()->delete();
            $receipt->delete();

            DB::commit();
            return response()->json(['message' => 'Réception annulée. Le BC est revenu dans la liste active pour correction.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors du retour au BC', 'error' => $e->getMessage()], 500);
        }
    }

    public function downloadInvoiceProxy(Receipt $receipt)
    {
        if (!$receipt->invoice_path) {
            return response()->json(['message' => 'Facture non attachée'], 404);
        }

        if (!Storage::disk('public')->exists($receipt->invoice_path)) {
            return response()->json(['message' => 'Fichier physique introuvable sur le disque'], 404);
        }

        return Storage::disk('public')->download($receipt->invoice_path);
    }
}
