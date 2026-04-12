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
        // On utilise Eloquent directement pour gérer les soft deletes et les relations proprement
        $query = StockUnit::query();
        
        if ($request->has('show_sold')) {
            $query->where('status', '=', 'vendue');
        } else {
            $query->where('status', '!=', 'vendue');
        }

        // Grouper manuellement après récupération pour garder la flexibilité
        $allUnits = $query->with('lot.variant.product')->get();
        
        $groups = $allUnits->groupBy(function($u) {
            return $u->lot_id . '_' . $u->status;
        });

        $result = $groups->map(function($group) {
            $first = $group->first();
            $unit = $first->toArray();
            $unit['quantity'] = $group->count();
            return $unit;
        })->values();

        return response()->json($result);
    }

    public function validateQA(Request $request)
    {
        // Maya: Robust input cleansing for dates
        $expirationDateInput = $request->input('expiration_date');
        $expirationDate = (empty($expirationDateInput) || $expirationDateInput === 'null' || $expirationDateInput === 'undefined') 
                          ? null 
                          : $expirationDateInput;

        try {
            $request->validate([
                'receipt_line_id' => 'required|exists:receipt_lines,id',
                'lot_number' => 'required|string',
                'expiration_date' => 'nullable|date',
                'serial_numbers' => 'nullable|array',
                'invoice_file' => 'nullable|file|mimes:pdf',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation des données transmises.',
                'errors' => $e->errors()
            ], 422);
        }

        $receiptLine = ReceiptLine::with(['receipt', 'poLine.variant.product'])->findOrFail($request->receipt_line_id);

        if ($receiptLine->status !== 'pending_qa') {
            return response()->json(['message' => 'Cette ligne n\'a pas besoin de validation (déjà validée ou annulée)'], 400);
        }

        if (!$receiptLine->poLine) {
            return response()->json(['message' => 'Erreur structurelle : Ligne de réception sans ligne de commande associée.'], 400);
        }

        DB::beginTransaction();
        try {
            // Gestion de la facture PDF si présente
            if ($request->hasFile('invoice_file')) {
                $path = $request->file('invoice_file')->store('purchase_invoices', 'public');
                $receiptLine->receipt->update([
                    'invoice_path' => $path,
                    'invoice_status' => 'received'
                ]);
            } else {
                // Maya: Only notify if the receipt doesn't have an invoice yet
                if ($receiptLine->receipt->invoice_status !== 'received') {
                    $receiptLine->receipt->update(['invoice_status' => 'pending']);
                    
                    // Avoid spamming notifications for multi-line receipts
                    $alreadyNotified = \App\Models\Notification::where('type', 'invoice_missing')
                        ->where('reference_id', $receiptLine->receipt->id)
                        ->where('created_at', '>', now()->subMinutes(5)) // Simple debounce
                        ->exists();

                    if (!$alreadyNotified) {
                        \App\Models\Notification::create([
                            'user_id' => $request->user()->id,
                            'type' => 'invoice_missing',
                            'title' => 'Validation sans facture : ' . $receiptLine->receipt->receipt_number,
                            'message' => 'Le stock pour ' . ($receiptLine->poLine?->variant?->product?->name ?? 'Produit') . ' a été validé sans import de facture.',
                            'reference_type' => 'Receipt',
                            'reference_id' => $receiptLine->receipt->id,
                        ]);
                    }
                }
            }

            $variantId = $receiptLine->poLine->variant_id;
            $purchasePrice = $receiptLine->actual_purchase_price ?? $receiptLine->poLine->unit_price ?? 0;

            // 1. Chercher ou créer le lot
            // Maya: Using a robust approach to avoid DB constraint issues
            $lot = Lot::where('lot_number', $request->lot_number)
                      ->where('variant_id', $variantId)
                      ->first();
            
            if (!$lot) {
                $lot = Lot::create([
                    'variant_id' => $variantId,
                    'lot_number' => $request->lot_number,
                    'expiration_date' => $expirationDate, // Si nullable en DB, OK. Sinon crash (mais migration créée)
                    'actual_purchase_price' => $purchasePrice,
                ]);
            }

            $quantity = $receiptLine->received_quantity;

            // 2. Générer les unités de stock
            for ($i = 0; $i < $quantity; $i++) {
                $serialNumber = (isset($request->serial_numbers[$i]) && !empty($request->serial_numbers[$i])) 
                                ? $request->serial_numbers[$i] 
                                : null;

                $unit = StockUnit::create([
                    'lot_id' => $lot->id,
                    'serial_number' => $serialNumber,
                    'status' => 'disponible',
                ]);

                // 3. Créer le mouvement de stock (Entrée)
                StockMovement::create([
                    'unit_id' => $unit->id,
                    'user_id' => $request->user()->id,
                    'type' => 'in',
                    'from_status' => null,
                    'to_status' => 'disponible',
                    'reference_doc_type' => ReceiptLine::class,
                    'reference_doc_id' => $receiptLine->id,
                ]);
            }

            // 4. Mettre à jour le statut de la ligne de réception
            $receiptLine->update(['status' => 'approved']);

            DB::commit();
            return response()->json([
                'message' => "Succès : $quantity unités ajoutées au lot $lot->lot_number.",
                'lot' => $lot
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur technique lors de la validation QA.',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTrace() : null
            ], 500);
        }
    }

    public function show(StockUnit $stockUnit)
    {
        return response()->json($stockUnit->load(['lot.variant.product', 'movements.user']));
    }
}
