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
        $query = Invoice::with(['order.client', 'order.lines.variant.product', 'payments']);
        if ($request->has('archived')) {
            $query->onlyTrashed();
        }
        return response()->json($query->get());
    }

    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'invoice_date' => 'nullable|date',
            'lines' => 'required|array',
            'lines.*.id' => 'required|exists:order_lines,id',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric'
        ]);

        $user = $request->user();
        if (!$user->role || !in_array($user->role->name, ['Admin', 'Gérant'])) {
            return response()->json(['message' => 'Accès refusé. Seul le Gérant ou l\'Admin peut modifier une facture.'], 403);
        }

        $order = $invoice->order;
        if (!$order) {
            return response()->json(['message' => 'Commande associée introuvable.'], 404);
        }

        DB::beginTransaction();
        try {
            // 1. Update basic info
            $invoice->update($request->only('invoice_date'));

            foreach ($request->lines as $lineData) {
                $orderLine = \App\Models\OrderLine::find($lineData['id']);
                if (!$orderLine) continue;

                $oldQty = (int)$orderLine->quantity;
                $newQty = (int)$lineData['quantity'];
                $variantId = $orderLine->variant_id;

                // Sync Stock based on quantity delta
                if ($newQty > $oldQty) {
                    // Need more items: withdraw extra
                    $diff = $newQty - $oldQty;
                    $availableUnits = \App\Models\StockUnit::whereHas('lot', function($q) use ($orderLine) {
                            $q->where('lot_number', $orderLine->lot_number);
                        })
                        ->where('status', 'disponible')
                        ->limit($diff)
                        ->get();
                    
                    if ($availableUnits->count() < $diff) {
                         DB::rollBack();
                         return response()->json(['message' => "Stock insuffisant pour augmenter la quantité. (Requis: {$diff}, Dispo: {$availableUnits->count()})"], 400);
                    }

                    foreach ($availableUnits as $unit) {
                        $unit->update(['status' => 'vendue']);
                    }
                } elseif ($newQty < $oldQty) {
                    // Reduced items: restore to stock
                    $diff = $oldQty - $newQty;
                    $soldUnits = \App\Models\StockUnit::whereHas('lot', function($q) use ($orderLine) {
                            $q->where('lot_number', $orderLine->lot_number);
                        })
                        ->where('status', 'vendue')
                        ->limit($diff)
                        ->get();
                    
                    foreach ($soldUnits as $unit) {
                        $unit->update(['status' => 'disponible']);
                    }
                }

                // Update the Order Line (BL)
                $orderLine->update([
                    'quantity' => $newQty,
                    'unit_price' => $lineData['unit_price']
                ]);

                // Sync the original Quote Line (Devis) if linked
                if ($order->quote_id) {
                    \App\Models\QuoteLine::where('quote_id', $order->quote_id)
                        ->where('variant_id', $variantId)
                        ->update([
                            'quantity' => $newQty,
                            'unit_price' => $lineData['unit_price']
                        ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Facture et stocks mis à jour avec succès.', 'invoice' => $invoice->load('order.lines')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la modification.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return response()->json(Invoice::withTrashed()->with(['order.lines.variant', 'payments', 'order.client'])->findOrFail($id));
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
}
