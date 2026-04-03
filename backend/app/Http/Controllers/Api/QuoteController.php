<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Order;
use App\Models\OrderLine;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\PublicQuoteRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $query = Quote::with(['client', 'lockedBy', 'lines.variant']);
        if ($request->has('archived')) {
            $query->onlyTrashed();
        }
        return response()->json($query->get());
    }

    public function generatePDF($id)
    {
        $quote = Quote::withTrashed()->with(['client', 'lines.variant.product'])->findOrFail($id);
        $pdf = $this->getQuotePdf($quote);
        return $pdf->download($quote->quote_number . '.pdf');
    }

    protected function getQuotePdf(Quote $quote)
    {
        $items = $quote->lines->map(function ($line) {
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
        $discountAmount = (float)($quote->discount_amount ?? 0);
        $totalTTC = $rawTTC - $discountAmount;

        $data = [
            'document_type' => 'Devis',
            'document_number' => $quote->quote_number,
            'document_date' => $quote->created_at,
            'client_id' => $quote->client_id,
            'client_name' => $quote->client->contact_name ?? ($quote->client->company_name ?? 'Client'),
            'client_ice' => $quote->client->ice,
            'client_address' => $quote->client->address,
            'client_city' => $quote->client->city,
            'client_tva' => $quote->client->tva ?? '',
            'items' => $items,
            'total_ht' => $totalHT,
            'total_tva' => $rawTTC - $totalHT,
            'discount_amount' => $discountAmount,
            'total_ttc' => $totalTTC,
            'payment_method' => 'Virement / Chèque',
            'condition_text' => '',
            'validity_date' => $quote->valid_until,
            'validity_message' => 'Devis valable jusqu\'au:',
            'deposit_text' => false,
            'balance_due' => $totalTTC,
            'title' => 'Devis ' . $quote->quote_number,
            'quote_number' => null, // Optionnel mais attendu par la vue
            'global_tva_rate' => 20
        ];

        return Pdf::loadView('pdf.document', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'valid_until' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'payment_term' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|exists:product_variants,id',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $quote = Quote::create([
                'client_id' => $request->client_id,
                'valid_until' => $request->valid_until,
                'discount_amount' => $request->discount_amount ?? 0,
                'payment_method' => $request->payment_method,
                'payment_term' => $request->payment_term,
                'status' => 'draft',
                'version' => 1
            ]);

            foreach ($request->lines as $line) {
                QuoteLine::create([
                    'quote_id' => $quote->id,
                    'variant_id' => $line['variant_id'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'discount_percent' => $line['discount_percent'] ?? 0,
                ]);
            }

            DB::commit();
            return response()->json($quote->load('lines.variant'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur création du devis', 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $quote = Quote::withTrashed()->with(['client', 'lockedBy', 'lines.variant'])->findOrFail($id);
        return response()->json($quote);
    }

    /**
     * Verrouiller le devis pour édition
     */
    public function lock(Request $request, Quote $quote)
    {
        if ($quote->locked_by_user_id !== null && $quote->locked_by_user_id !== $request->user()->id) {
            return response()->json(['message' => 'Ce devis est déjà en cours d\'édition par un autre utilisateur.'], 423); // 423 Locked
        }

        $quote->update([
            'locked_by_user_id' => $request->user()->id,
            'locked_at' => now()
        ]);

        return response()->json(['message' => 'Devis verrouillé pour édition.', 'quote' => $quote]);
    }

    /**
     * Déverrouiller le devis
     */
    public function unlock(Request $request, Quote $quote)
    {
        if ($quote->locked_by_user_id === $request->user()->id || $request->user()->role->name === 'Admin') {
            $quote->update([
                'locked_by_user_id' => null,
                'locked_at' => null
            ]);
            return response()->json(['message' => 'Devis déverrouillé.']);
        }
        
        return response()->json(['message' => 'Vous ne pouvez pas déverrouiller ce devis.'], 403);
    }

    /**
     * Confirmer et convertir en Commande
     */
    public function confirm(Quote $quote)
    {
        if ($quote->status !== 'draft') {
            return response()->json(['message' => 'Seul un devis brouillon peut être confirmé.'], 400);
        }

        DB::beginTransaction();
        try {
            $quote->update(['status' => 'confirmed']);

            // Transformation en Bon de Livraison (Table Orders)
            $order = Order::create([
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'order_number' => 'BL-' . date('ym') . '-' . str_pad(Order::count() + 1, 4, '0', STR_PAD_LEFT),
                'status' => 'pending', // En attente de livraison/expédition
                'discount_amount' => $quote->discount_amount ?? 0,
            ]);

            foreach ($quote->lines as $line) {
                OrderLine::create([
                    'order_id' => $order->id,
                    'variant_id' => $line->variant_id,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => 20.00, // Par défaut ou dynamisé si ajouté dans db
                    'discount_percent' => $line->discount_percent,
                ]);
            }

            $quote->delete();

            DB::commit();
            return response()->json(['message' => 'Devis confirmé et transformé en Bon de Livraison !', 'quote' => $quote, 'order' => $order]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la transformation en BL.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mettre à jour un devis existant
     */
    public function update(Request $request, Quote $quote)
    {
        if (!in_array($quote->status, ['draft', 'À confirmer'])) {
            return response()->json(['message' => 'Seul un devis brouillon ou en attente peut être modifié.'], 400);
        }

        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'valid_until' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string',
            'payment_term' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|exists:product_variants,id',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            $quote->update([
                'client_id' => $request->client_id,
                'valid_until' => $request->valid_until,
                'discount_amount' => $request->discount_amount ?? 0,
                'payment_method' => $request->payment_method,
                'payment_term' => $request->payment_term,
                'version' => $quote->version + 1
            ]);

            // Supprimer les anciennes lignes et insérer les nouvelles
            $quote->lines()->delete();

            foreach ($request->lines as $line) {
                QuoteLine::create([
                    'quote_id' => $quote->id,
                    'variant_id' => $line['variant_id'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'discount_percent' => $line['discount_percent'] ?? 0,
                ]);
            }

            // Déverrouiller automatiquement après sauvegarde
            $quote->update(['locked_by_user_id' => null, 'locked_at' => null]);

            DB::commit();
            return response()->json($quote->load('lines.variant'), 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la modification du devis', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Supprimer un devis
     */
    public function destroy(Quote $quote)
    {
        if (!in_array($quote->status, ['draft', 'À confirmer'])) {
            return response()->json(['message' => 'Seul un devis brouillon ou en attente peut être supprimé.'], 400);
        }

        DB::beginTransaction();
        try {
            $quote->lines()->delete();
            $quote->delete();
            DB::commit();
            return response()->json(['message' => 'Devis supprimé avec succès']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la suppression', 'error' => $e->getMessage()], 500);
        }
    }

}
