<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with([
            'order' => function($q) { $q->withTrashed(); },
            'order.lines' => function($q) { $q->withTrashed(); },
            'order.client',
            'order.lines.variant.product',
            'payments'
        ]);
        if ($request->has('archived')) {
            $query->onlyTrashed();
        }
        return response()->json($query->get());
    }

    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'invoice_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'payment_method' => 'nullable|string',
            'lines' => 'required|array',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric'
        ]);

        $user = $request->user();
        if (!$user->role || !in_array($user->role->name, ['Admin', 'Gérant'])) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        $order = $invoice->order()->withTrashed()->first();
        if (!$order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        DB::beginTransaction();
        try {
            // 1. Update basic info
            $invoice->update($request->only('invoice_date', 'due_date', 'payment_method'));

            $requestedLineIds = collect($request->lines)->pluck('id')->filter()->toArray();
            $existingLines = $order->lines()->withTrashed()->get();

            // 2. Handle Deletions (Lines in DB not in request)
            foreach ($existingLines as $existingLine) {
                if (!in_array($existingLine->id, $requestedLineIds)) {
                     // Restore ALL stock for this line
                     \App\Models\StockUnit::whereHas('lot', function($q) use ($existingLine) {
                             if ($existingLine->lot_number) {
                                 $q->where('lot_number', $existingLine->lot_number);
                             } else {
                                 $q->where('variant_id', $existingLine->variant_id);
                             }
                         })
                         ->where('status', 'vendue')
                         ->limit($existingLine->quantity)
                         ->update(['status' => 'disponible']);
                     
                     // Delete line from Order and Quote
                     $vId = $existingLine->variant_id;
                     $existingLine->forceDelete(); // Truly remove if we are "correcting" an invoice
                     if ($order->quote_id) {
                         \App\Models\QuoteLine::withTrashed()
                             ->where('quote_id', $order->quote_id)
                             ->where('variant_id', $vId)
                             ->forceDelete();
                     }
                }
            }

            // 3. Handle Updates and Additions
            foreach ($request->lines as $lineData) {
                if (isset($lineData['id'])) {
                    // UPDATE EXISTING
                    $orderLine = \App\Models\OrderLine::withTrashed()->find($lineData['id']);
                    if (!$orderLine) continue;

                    $oldQty = (int)$orderLine->quantity;
                    $newQty = (int)$lineData['quantity'];

                    $snListForUpdate = $orderLine->serial_numbers ? array_map('trim', explode(',', $orderLine->serial_numbers)) : [];
                    
                    if ($newQty > $oldQty) {
                        $diff = $newQty - $oldQty;
                        $units = \App\Models\StockUnit::whereHas('lot', function($q) use ($orderLine) {
                                if ($orderLine->lot_number) {
                                    $q->where('lot_number', $orderLine->lot_number);
                                } else {
                                    $q->where('variant_id', $orderLine->variant_id);
                                }
                            })
                            ->where('status', 'disponible')
                            ->orderBy('created_at', 'asc') // FIFO
                            ->limit($diff)
                            ->get();
                        if ($units->count() < $diff) throw new \Exception("Stock insuffisant pour {$orderLine->variant_id}");
                        foreach ($units as $u) {
                            $u->update(['status' => 'vendue']);
                            if ($u->serial_number) $snListForUpdate[] = $u->serial_number;
                        }
                    } elseif ($newQty < $oldQty) {
                        $diff = $oldQty - $newQty;
                        $restoredUnits = \App\Models\StockUnit::whereHas('lot', function($q) use ($orderLine) {
                                if ($orderLine->lot_number) {
                                    $q->where('lot_number', $orderLine->lot_number);
                                } else {
                                    $q->where('variant_id', $orderLine->variant_id);
                                }
                            })
                            ->where('status', 'vendue')
                            ->orderBy('updated_at', 'desc') // Remove recently sold first
                            ->limit($diff)
                            ->get();
                        
                        foreach ($restoredUnits as $u) {
                            $u->update(['status' => 'disponible']);
                            if ($u->serial_number && ($key = array_search($u->serial_number, $snListForUpdate)) !== false) {
                                unset($snListForUpdate[$key]);
                            }
                        }
                    }

                    $orderLine->update([
                        'serial_numbers' => !empty($snListForUpdate) ? implode(', ', $snListForUpdate) : null,
                        'quantity' => $newQty,
                        'unit_price' => $lineData['unit_price'],
                        'discount_percent' => $lineData['discount_percent'] ?? 0,
                        'tax_rate' => $lineData['tax_rate'] ?? 20
                    ]);

                    if ($order->quote_id) {
                        \App\Models\QuoteLine::withTrashed()->where('quote_id', $order->quote_id)->where('variant_id', $orderLine->variant_id)
                            ->update(['quantity' => $newQty, 'unit_price' => $lineData['unit_price'], 'discount_percent' => $lineData['discount_percent'] ?? 0, 'tax_rate' => $lineData['tax_rate'] ?? 20]);
                    }
                } else {
                    // ADD NEW LINE
                    $variantId = $lineData['variant_id'];
                    $qty = (int)$lineData['quantity'];

                    // FIFO Stock withdrawal
                    $availableUnits = \App\Models\StockUnit::with('lot')->whereHas('lot', function($q) use ($variantId) {
                            $q->where('variant_id', $variantId);
                        })
                        ->where('status', 'disponible')
                        ->orderBy('created_at', 'asc') // FIFO
                        ->limit($qty)
                        ->get();

                    if ($availableUnits->count() < $qty) throw new \Exception("Stock insuffisant pour le nouveau produit variant #{$variantId}");

                    $lotNumber = $availableUnits->first()->lot->lot_number;
                    $snList = [];
                    foreach ($availableUnits as $u) {
                        $u->update(['status' => 'vendue']);
                        if ($u->serial_number) $snList[] = $u->serial_number;
                    }

                    $newOrderLine = \App\Models\OrderLine::create([
                        'order_id' => $order->id,
                        'variant_id' => $variantId,
                        'lot_number' => $lotNumber,
                        'serial_numbers' => !empty($snList) ? implode(', ', $snList) : null,
                        'quantity' => $qty,
                        'unit_price' => $lineData['unit_price'],
                        'discount_percent' => $lineData['discount_percent'] ?? 0,
                        'tax_rate' => $lineData['tax_rate'] ?? 20
                    ]);

                    if ($order->quote_id) {
                        \App\Models\QuoteLine::create([
                            'quote_id' => $order->quote_id,
                            'variant_id' => $variantId,
                            'quantity' => $qty,
                            'unit_price' => $lineData['unit_price'],
                            'discount_percent' => $lineData['discount_percent'] ?? 0,
                            'tax_rate' => $lineData['tax_rate'] ?? 20
                        ]);
                    }
                }
            }

            // --- RECALCUL FINANCES ---
            $newTotal = 0;
            foreach ($order->lines as $line) {
                $ht = ($line->quantity * $line->unit_price) * (1 - ($line->discount_percent / 100));
                $newTotal += $ht * (1 + ($line->tax_rate / 100));
            }
            $totalPaid = $invoice->payments()->sum('amount');
            $newBalance = max(0, $newTotal - $totalPaid);
            $newStatus = 'sent';
            if ($newBalance <= 0) $newStatus = 'paid';
            elseif ($totalPaid > 0) $newStatus = 'partially_paid';

            $invoice->update([
                'total_amount' => $newTotal,
                'balance_due' => $newBalance,
                'status' => $newStatus
            ]);

            DB::commit();
            return response()->json(['message' => 'Facture actualisée avec succès.', 'invoice' => $invoice->load(['order' => fn($q) => $q->withTrashed(), 'order.lines' => fn($q) => $q->withTrashed(), 'payments'])]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Invoice::withTrashed()->with([
            'order' => fn($q) => $q->withTrashed(),
            'order.lines' => fn($q) => $q->withTrashed(),
            'order.lines.variant.product',
            'payments',
            'order.client'
        ])->findOrFail($id));
    }

    public function generatePDF($id)
    {
        $invoice = Invoice::withTrashed()->with(['order' => function($q) { $q->withTrashed(); }, 'order.client', 'order.lines.variant.product', 'payments'])->findOrFail($id);
        $order = $invoice->order;

        $items = $order->lines->map(function ($line) {
            $ht = $line->unit_price * $line->quantity;
            $tvaRate = (float)($line->variant?->tva_rate ?? 20);
            $discountPercent = (float)($line->discount_percent ?? 0);
            $discountVal = $ht * ($discountPercent / 100);
            $netHT = $ht - $discountVal;
            $ttc = $netHT * (1 + ($tvaRate / 100));

            return [
                'reference' => $line->variant?->sku ?? 'N/A',
                'name' => ($line->variant?->product?->name ?? 'Produit') . ($line->variant?->product?->brand ? ' - ' . $line->variant->product->brand : '') . ($line->variant?->name_variation ? ' - ' . $line->variant->name_variation : ''),
                'description' => '',
                'unit' => $line->variant?->product?->unit ?? 'U',
                'quantity' => $line->quantity,
                'unit_price_ht' => $line->unit_price,
                'unit_price_ttc' => $line->unit_price * (1 + ($tvaRate / 100)),
                'discount_percent' => $discountPercent,
                'discount' => $discountPercent > 0 ? (round($discountPercent, 2) . '%') : '0%',
                'total_ht' => $netHT,
                'total_ttc' => $ttc,
                'tva' => $tvaRate
            ];
        });

        $tvaBreakdown = $items->groupBy('tva')->map(function ($group, $rate) {
            return [
                'rate' => $rate,
                'ht' => $group->sum('total_ht'),
                'tva' => $group->sum('total_ttc') - $group->sum('total_ht')
            ];
        });

        $totalHT = $items->sum('total_ht');
        $rawTTC = $items->sum('total_ttc');
        $discountAmount = (float)($invoice->discount_amount ?? 0);
        $totalTTC = $rawTTC - $discountAmount; // Final après remise globale

        // Calcul des paiements
        $totalPaid = collect($invoice->payments)->sum('amount');
        $balanceDue = $totalTTC - $totalPaid;

        $data = [
            'document_type' => 'Facture',
            'document_number' => $invoice->invoice_number,
            'document_date' => $invoice->invoice_date ?? $invoice->created_at,
            'client_id' => $order->client_id,
            'client_name' => $order->client->company_name ?? $order->client->contact_name,
            'client_contact' => $order->client->contact_name,
            'client_ice' => $order->client->ice,
            'client_address' => $order->client->address,
            'client_city' => $order->client->city,
            'client_tva' => $order->client->tva ?? '',
            'items' => $items,
            'total_ht' => $totalHT,
            'total_tva' => $rawTTC - $totalHT,
            'tva_breakdown' => $tvaBreakdown,
            'discount_amount' => $discountAmount,
            'total_ttc' => $totalTTC,
            'payment_method' => $invoice->payment_method ?? 'Virement / Espèce',
            'condition_text' => 'Avance à la livraison et le reste selon accords.',
            'validity_date' => null,
            'validity_message' => '',
            'deposit_text' => true,
            'total_paid' => $totalPaid,
            'balance_due' => $balanceDue,
            'title' => 'Facture_' . $invoice->invoice_number,
            'order_number' => $order->order_number,
            'global_tva_rate' => count($tvaBreakdown) === 1 ? $tvaBreakdown->first()['rate'] : null
        ];

        $pdf = Pdf::loadView('pdf.document', $data);
        return $pdf->download($invoice->invoice_number . '.pdf');
    }
    // Usually invoices are generated automatically from Orders in this system, 
    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return response()->json(['message' => 'Facture archivée avec succès.']);
    }

    public function restore(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->role || !in_array($user->role->name, ['Admin', 'Gérant'])) {
            return response()->json(['message' => 'Accès refusé. Seuls l\'Admin et le Gérant peuvent restaurer une facture.'], 403);
        }

        $invoice = Invoice::withTrashed()->findOrFail($id);

        DB::beginTransaction();
        try {
            // Restore the invoice if it was soft-deleted
            if ($invoice->trashed()) {
                $invoice->restore();
            }

            // IMPORTANT: We do NOT restore order/quote because they should stay archived
            // The update() method already handles trashed records if needed.
            $order = $invoice->order()->withTrashed()->first();
            
            // 1. Delete all associated payments
            $invoice->payments()->delete();

            // 2. Reset status and balance
            $invoice->update([
                'status' => 'unpaid',
                'balance_due' => $invoice->total_amount
            ]);

            DB::commit();
            return response()->json(['message' => 'Facture restaurée et réinitialisée avec succès.', 'invoice' => $invoice]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la restauration : ' . $e->getMessage()], 500);
        }
    }
}
