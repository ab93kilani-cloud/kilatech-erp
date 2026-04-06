<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with(['order.client', 'payments']);
        if ($request->has('archived')) {
            $query->onlyTrashed();
        }
        return response()->json($query->get());
    }

    public function update(Request $request, Invoice $invoice)
    {
        $request->validate(['invoice_date' => 'nullable|date']);
        $invoice->update($request->only('invoice_date'));
        return response()->json(['message' => 'Date mise à jour.', 'invoice' => $invoice]);
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
