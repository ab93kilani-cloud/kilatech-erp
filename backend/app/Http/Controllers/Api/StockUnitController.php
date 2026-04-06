<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Models\ReceiptLine;
use App\Models\StockMovement;
use App\Models\StockUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockUnitController extends Controller
{
    public function index(Request $request)
    {
        // On groupe par lot et statut pour éviter de renvoyer 10000 lignes pour 10000 aiguilles.
        $query = DB::table('stock_units')
            ->select('lot_id', 'status', DB::raw('count(id) as quantity'), DB::raw('MAX(id) as sample_id'))
            ->whereNull('deleted_at');
        
        if ($request->has('show_sold')) {
            $query->where('status', '=', 'vendue');
        } else {
            $query->where('status', '!=', 'vendue');
        }

        $summary = $query->groupBy('lot_id', 'status')->get();

        $sampleIds = $summary->pluck('sample_id');
        
        $sampleUnits = StockUnit::with([
            'lot.variant.product',
            'movements' => function($q) {
                $q->where('type', 'in')->with('document.poLine.purchaseOrder.supplier');
            }
        ])->whereIn('id', $sampleIds)->get()->keyBy('id');

        $result = $summary->map(function($item) use ($sampleUnits) {
            if (!isset($sampleUnits[$item->sample_id])) return null;
            $unit = $sampleUnits[$item->sample_id]->toArray();
            $unit['quantity'] = $item->quantity;
            $unit['serial_number'] = $item->quantity > 1 ? 'Multiple (Bulk)' : $unit['serial_number'];
            return $unit;
        })->filter()->values();

        return response()->json($result);
    }

    /**
     * Valide une ligne de réception et génère les unités de stock.
     */
    public function validateQA(Request $request)
    {
        $request->validate([
            'receipt_line_id' => 'required|exists:receipt_lines,id',
            'lot_number' => 'required|string',
            'expiration_date' => 'required|date',
            'serial_numbers' => 'nullable|array',
            'invoice_file' => 'nullable|file|mimes:pdf',
        ]);

        $receiptLine = ReceiptLine::with('receipt')->findOrFail($request->receipt_line_id);

        if ($receiptLine->status !== 'pending_qa') {
            return response()->json(['message' => 'Cette ligne n\'est pas en attente de validation QA'], 400);
        }

        DB::beginTransaction();
        try {
            // Gestion de la facture PDF
            if ($request->hasFile('invoice_file')) {
                $path = $request->file('invoice_file')->store('purchase_invoices', 'public');
                $receiptLine->receipt->update([
                    'invoice_path' => $path,
                    'invoice_status' => 'received'
                ]);
            } else {
                // Alerte si la facture est manquante
                $receiptLine->receipt->update(['invoice_status' => 'pending']);
                
                \App\Models\Notification::create([
                    'user_id' => $request->user()->id,
                    'type' => 'invoice_missing',
                    'title' => 'Facture manquante : ' . $receiptLine->receipt->receipt_number,
                    'message' => 'Le stock pour ' . ($receiptLine->poLine?->variant?->product?->name ?? 'Produit') . ' a été validé sans facture fournisseur.',
                    'reference_type' => 'Receipt',
                    'reference_id' => $receiptLine->receipt->id,
                ]);
            }
            $variantId = $receiptLine->poLine->variant_id;

            // 1. Chercher ou créer le lot
            $lot = Lot::firstOrCreate(
                ['lot_number' => $request->lot_number, 'variant_id' => $variantId],
                [
                    'expiration_date' => $request->expiration_date,
                    'actual_purchase_price' => $receiptLine->actual_purchase_price,
                ]
            );

            $quantity = $receiptLine->received_quantity;

            // 2. Générer les unités de stock
            $unitsToCreate = [];
            for ($i = 0; $i < $quantity; $i++) {
                $serialNumber = null;
                if (!empty($request->serial_numbers) && isset($request->serial_numbers[$i])) {
                    $serialNumber = $request->serial_numbers[$i];
                }

                $unit = StockUnit::create([
                    'lot_id' => $lot->id,
                    'serial_number' => $serialNumber,
                    'status' => 'disponible',
                ]);

                // 3. Créer le mouvement de stock (IN)
                StockMovement::create([
                    'unit_id' => $unit->id,
                    'user_id' => $request->user()->id,
                    'type' => 'in',
                    'from_status' => null,
                    'to_status' => 'disponible',
                    'reference_doc_type' => ReceiptLine::class,
                    'reference_doc_id' => $receiptLine->id,
                ]);

                $unitsToCreate[] = $unit;
            }

            // 4. Mettre à jour le statut
            $receiptLine->update(['status' => 'approved']);

            DB::commit();
            return response()->json(['message' => "$quantity unités générées avec succès liées au lot $lot->lot_number"], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la validation QA', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(StockUnit $stockUnit)
    {
        return response()->json($stockUnit->load(['lot.variant.product', 'movements.user']));
    }
}
