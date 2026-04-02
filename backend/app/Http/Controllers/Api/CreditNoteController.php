<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Lot;
use App\Models\StockUnit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditNoteController extends Controller
{
    public function index(Request $request)
    {
        $query = CreditNote::with(['client', 'invoice']);
        if ($request->has('archived')) {
            $query->onlyTrashed();
        }
        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'total_amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string'
        ]);

        try {
            if ($request->filled('invoice_id')) {
                $invoice = Invoice::findOrFail($request->invoice_id);
                if ($invoice->balance_due > 0 && $invoice->status !== 'paid') {
                    return response()->json([
                        'message' => 'Impossible de créer un avoir pour une facture qui n\'est pas encore totalement réglée.'
                    ], 422);
                }
            }

            DB::beginTransaction();

            // Generate Credit Note Number
            $lastCreditNote = CreditNote::orderBy('id', 'desc')->first();
            $nextId = $lastCreditNote ? $lastCreditNote->id + 1 : 1;
            $cnNumber = 'AV-' . date('ym') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

            $creditNote = CreditNote::create([
                'credit_note_number' => $cnNumber,
                'client_id' => $request->client_id,
                'invoice_id' => $request->invoice_id,
                'total_amount' => $request->total_amount,
                'reason' => $request->reason,
                'status' => 'validated'
            ]);

            // Retour des produits en stock si lié à une facture
            if ($request->filled('invoice_id')) {
                // Charger la commande et ses lignes
                $order = $invoice->order()->with('lines')->first();
                if ($order && $order->lines) {
                    foreach ($order->lines as $line) {
                        // Chercher ou créer un Lot générique de "Retour Avoir" pour cette variante
                        $returnLot = Lot::firstOrCreate(
                            [
                                'variant_id' => $line->variant_id,
                                'lot_number' => 'RET-' . date('Ym') . '-' . $line->variant_id
                            ],
                            [
                                'expiration_date' => null, // Pas d'expiration stricte par défaut
                                'actual_purchase_price' => $line->unit_price
                            ]
                        );

                        // Recréer les unités de stock retournées
                        for ($i = 0; $i < $line->quantity; $i++) {
                            StockUnit::create([
                                'lot_id' => $returnLot->id,
                                'serial_number' => 'RET-' . strtoupper(Str::random(8)),
                                'status' => 'available'
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json(['message' => 'Avoir créé avec succès', 'credit_note' => $creditNote], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur technique: ' . $e->getMessage()], 500);
        }
    }

    public function generatePDF($id)
    {
        $creditNote = CreditNote::with(['client', 'invoice.order.lines.variant.product'])->findOrFail($id);

        $items = collect([]);
        $totalHT = 0;
        $totalTTC = 0;
        $totalTVA = 0;

        // Si l'avoir est lié à une facture, on affiche les lignes retournées
        if ($creditNote->invoice && $creditNote->invoice->order && $creditNote->invoice->order->lines) {
            $items = $creditNote->invoice->order->lines->map(function ($line) {
                $ht = $line->unit_price * $line->quantity;
                $tvaRate = (float)($line->variant?->tva_rate ?? 20);
                $discountPercent = (float)($line->discount_percent ?? 0);
                $discountVal = $ht * ($discountPercent / 100);
                $netHT = $ht - $discountVal;
                $ttc = $netHT * (1 + ($tvaRate / 100));

                return [
                    'reference' => $line->variant?->sku ?? 'N/A',
                    'name' => ($line->variant?->product?->name ?? 'Produit') . ($line->variant?->name_variation ? ' - ' . $line->variant->name_variation : ''),
                    'description' => 'Retour Avoir',
                    'quantity' => $line->quantity,
                    'unit_price_ht' => $line->unit_price,
                    'unit_price_ttc' => $line->unit_price * (1 + ($tvaRate / 100)),
                    'discount' => $discountPercent > 0 ? number_format($discountPercent, 2) . '%' : '-',
                    'total_ht' => $netHT,
                    'total_ttc' => $ttc,
                    'tva' => $tvaRate
                ];
            });

            $totalHT = $items->sum('total_ht');
            $totalTTC = $items->sum('total_ttc');
            $totalTVA = $totalTTC - $totalHT; // On recalcule ou on prend depuis sum
        } else {
            // Avoir générique sans facture
            $totalTTC = $creditNote->total_amount;
            $totalHT = $totalTTC / 1.20; // Approximation si non spécifique
            $totalTVA = $totalTTC - $totalHT;
            $items->push([
                'reference' => 'AVOIR',
                'name' => 'Avoir global',
                'description' => $creditNote->reason ?? '',
                'quantity' => 1,
                'unit_price_ht' => $totalHT,
                'unit_price_ttc' => $totalTTC,
                'total_ht' => $totalHT,
                'total_ttc' => $totalTTC,
                'tva' => 20
            ]);
        }

        $data = [
            'document_type' => 'Avoir',
            'document_number' => $creditNote->credit_note_number,
            'document_date' => $creditNote->created_at,
            'client_id' => $creditNote->client_id,
            'client_name' => $creditNote->client->company_name ?? $creditNote->client->contact_name,
            'client_ice' => $creditNote->client->ice,
            'client_address' => $creditNote->client->address,
            'client_city' => $creditNote->client->city,
            'client_tva' => $creditNote->client->tva ?? '',
            'items' => $items,
            'total_ht' => $totalHT,
            'total_tva' => $totalTVA,
            'total_ttc' => $totalTTC,
            'payment_method' => 'N/A',
            'condition_text' => 'Avoir applicable sur vos prochaines factures.',
            'validity_date' => null,
            'validity_message' => '',
            'deposit_text' => false,
            'total_paid' => 0,
            'balance_due' => $totalTTC,
            'title' => 'Avoir_' . $creditNote->credit_note_number
        ];

        $pdf = Pdf::loadView('pdf.document', $data);
        return $pdf->download($creditNote->credit_note_number . '.pdf');
    }
}
