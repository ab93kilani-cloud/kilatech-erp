<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Quote;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['client', 'quote', 'lines.variant']);
        if ($request->has('archived')) {
            $query->onlyTrashed();
        }
        return response()->json($query->get());
    }

    public function generatePDF($id)
    {
        $order = Order::withTrashed()->with(['client', 'quote' => function($q) { $q->withTrashed(); }, 'lines.variant.product'])->findOrFail($id);

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
                'quantity' => $line->quantity,
                'unit_price_ht' => $line->unit_price,
                'unit_price_ttc' => $line->unit_price * (1 + ($tvaRate / 100)),
                'discount' => $discountPercent > 0 ? (round($discountPercent, 2) . '%') : '0%',
                'total_ht' => $netHT,
                'total_ttc' => $ttc,
                'tva' => $tvaRate
            ];
        });

        $totalHT = $items->sum('total_ht');
        $rawTTC = $items->sum('total_ttc');
        $discountAmount = (float)($order->discount_amount ?? 0);
        $totalTTC = $rawTTC - $discountAmount;

        $data = [
            'document_type' => 'Bon de livraison',
            'document_number' => $order->order_number,
            'quote_number' => $order->quote ? $order->quote->quote_number : null,
            'document_date' => $order->created_at,
            'client_id' => $order->client_id,
            'client_name' => $order->client->company_name ?? $order->client->contact_name,
            'client_ice' => $order->client->ice,
            'client_address' => $order->client->address,
            'client_city' => $order->client->city,
            'client_tva' => $order->client->tva ?? '',
            'items' => $items,
            'total_ht' => $totalHT,
            'total_tva' => $rawTTC - $totalHT, // TVA avant remise globale (ou répartie, simplifions ici)
            'discount_amount' => $discountAmount,
            'total_ttc' => $totalTTC,
            'payment_method' => 'Espèce',
            'condition_text' => '',
            'validity_date' => null,
            'validity_message' => '',
            'deposit_text' => true,
            'balance_due' => $totalTTC, // ou calculer si acompte
            'title' => 'Bon_Livraison_' . $order->order_number,
            'copies' => ['EXEMPLAIRE CLIENT', 'EXEMPLAIRE SOCIÉTÉ / ACCUSÉ DE RÉCEPTION']
        ];

        $pdf = Pdf::loadView('pdf.document', $data);
        return $pdf->download($order->order_number . '.pdf');
    }

    /**
     * Convertir un devis confirmé en Commande officielle
     */
    public function storeFromQuote(Request $request)
    {
        $request->validate([
            'quote_id' => 'required|exists:quotes,id',
        ]);

        $quote = Quote::with('lines')->findOrFail($request->quote_id);

        if ($quote->status !== 'confirmed') {
            return response()->json(['message' => 'Le devis doit d\'abord être confirmé.'], 400);
        }
        
        // Prevent duplicate creation
        if (Order::where('quote_id', $quote->id)->exists()) {
            return response()->json(['message' => 'Une commande existe déjà pour ce devis.'], 400);
        }

        DB::beginTransaction();
        try {
            $orderNumber = 'BL-' . date('ym') . '-' . str_pad(Order::count() + 1, 4, '0', STR_PAD_LEFT);

            $order = Order::create([
                'quote_id' => $quote->id,
                'client_id' => $quote->client_id,
                'order_number' => $orderNumber,
                'status' => 'confirmed',
                'discount_amount' => $quote->discount_amount ?? 0,
            ]);

            $totalAmount = 0;

            foreach ($quote->lines as $line) {
                OrderLine::create([
                    'order_id' => $order->id,
                    'variant_id' => $line->variant_id,
                    'unit_price' => $line->unit_price,
                    'quantity' => $line->quantity,
                    'discount_percent' => $line->discount_percent,
                ]);

                $discountPriceHT = $line->unit_price * (1 - ($line->discount_percent / 100));
                $tvaRate = $line->variant?->tva_rate ?? 20;
                $lineTTC = $discountPriceHT * $line->quantity * (1 + ($tvaRate / 100));
                $totalAmount += $lineTTC;
            }

            // Génération de la facture initiale (en supposant $totalAmount global et discount_amount global)
            $totalAmount -= $quote->discount_amount ?? 0; // Appliquer aussi la remise globale du devis si elle existe

            $invoiceNumber = 'FA-' . date('ym') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
            Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount, // Total to pay initially
                'status' => 'unpaid',
                'discount_amount' => $quote->discount_amount ?? 0,
            ]);

            DB::commit();
            
            return response()->json($order->load(['lines', 'invoice']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la conversion', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $order = Order::withTrashed()->with(['client', 'quote' => function($q) { $q->withTrashed(); }, 'lines.variant', 'invoice' => function($q) { $q->withTrashed(); }])->findOrFail($id);
        return response()->json($order);
    }

    /**
     * Annule la Commande/BL et remet le devis en "brouillon"
     */
    public function revertToQuote(Order $order)
    {
        DB::beginTransaction();
        try {
            if ($order->invoice && $order->invoice->status !== 'cancelled') {
                return response()->json(['message' => 'Impossible d\'annuler ce BL car une facture y est déjà rattachée.'], 400);
            }

            if ($order->quote) {
                $order->quote->update(['status' => 'draft']);
            }

            $order->lines()->delete();
            $order->delete();

            DB::commit();
            return response()->json(['message' => 'Le Bon de Livraison a été annulé et le devis est repassé en brouillon.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors du retour en devis.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Génère une Facture (Invoice) à partir d'un Bon de Livraison
     */
    public function generateInvoice(Order $order)
    {
        if ($order->invoice) {
            return response()->json(['message' => 'Une facture a déjà été générée pour ce Bon de Livraison.', 'invoice' => $order->invoice], 400);
        }

        DB::beginTransaction();
        try {
            $totalAmount = 0;
            foreach ($order->lines as $line) {
                $totalAmount += ($line->unit_price * $line->quantity);
            }

            $invoiceNumber = 'FA-' . date('ym') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
            
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount,
                'status' => 'unpaid' // En attente de paiement
            ]);

            $order->delete();

            DB::commit();
            return response()->json(['message' => 'Facture générée avec succès.', 'invoice' => $invoice], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la facturation.', 'error' => $e->getMessage()], 500);
        }
    }
}
