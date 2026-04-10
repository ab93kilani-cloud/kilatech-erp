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
use Illuminate\Support\Facades\Schema;
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
                'unit' => $line->variant?->product?->unit ?? 'U',
                'client_product_ref' => $line->client_product_ref,
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
        $discountAmount = (float)($quote->discount_amount ?? 0);
        $totalTTC = $rawTTC - $discountAmount;

        $data = [
            'document_type' => 'Devis',
            'document_number' => $quote->quote_number,
            'document_date' => $quote->quote_date ?? $quote->created_at,
            'client_id' => $quote->client_id,
            'client_name' => $quote->client->company_name ?? ($quote->client->contact_name ?? 'Client'),
            'client_contact' => $quote->client->contact_name,
            'client_ice' => $quote->client->ice,
            'client_address' => $quote->client->address,
            'client_city' => $quote->client->city,
            'client_tva' => $quote->client->tva ?? '',
            'items' => $items,
            'total_ht' => $totalHT,
            'total_tva' => $rawTTC - $totalHT,
            'tva_breakdown' => $tvaBreakdown,
            'discount_amount' => $discountAmount,
            'total_ttc' => $totalTTC,
            'payment_method' => $quote->payment_method ?? 'Virement / Chèque',
            'condition_text' => '',
            'validity_date' => $quote->valid_until,
            'validity_message' => 'Devis valable jusqu\'au:',
            'deposit_text' => false,
            'balance_due' => $totalTTC,
            'title' => 'Devis ' . $quote->quote_number,
            'quote_number' => null,
            'global_tva_rate' => count($tvaBreakdown) === 1 ? $tvaBreakdown->first()['rate'] : null
        ];

        return Pdf::loadView('pdf.document', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'valid_until' => 'nullable|date',
            'quote_date' => 'nullable|date',
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
                'quote_date' => $request->quote_date ?? \Carbon\Carbon::now()->toDateString(),
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
                    'tax_rate' => $line['tax_rate'] ?? 20.00,
                ]);
            }

            DB::commit();
            return response()->json($quote->load('lines.variant'), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur création du devis', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $quote = Quote::findOrFail($id);
        
        $validated = $request->validate([
            'client_id' => 'nullable|exists:clients,id',
            'valid_until' => 'nullable|date',
            'quote_date' => 'nullable|date',
            'discount_amount' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'source' => 'nullable|string|in:erp,web',
            'lines' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            $quote->update($request->only(['client_id', 'valid_until', 'quote_date', 'discount_amount', 'status', 'source']));

            if ($request->has('lines')) {
                $quote->lines()->delete();
                foreach ($request->lines as $line) {
                    QuoteLine::create([
                        'quote_id' => $quote->id,
                        'variant_id' => $line['variant_id'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'discount_percent' => $line['discount_percent'] ?? 0,
                        'tax_rate' => $line['tax_rate'] ?? 20.00,
                    ]);
                }
            }

            DB::commit();
            return response()->json($quote->load('lines.variant'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur modification du devis'], 500);
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

            $docDate = $quote->quote_date ? \Carbon\Carbon::parse($quote->quote_date) : \Carbon\Carbon::now();
            $prefix = 'BL-' . $docDate->format('ym');
            $count = Order::withTrashed()->where('order_number', 'like', $prefix . '%')->count();

            $order = Order::create([
                'client_id' => $quote->client_id,
                'quote_id' => $quote->id,
                'order_number' => $prefix . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT),
                'order_date' => $docDate->toDateString(),
                'status' => 'pending', 
                'discount_amount' => $quote->discount_amount ?? 0,
                'payment_method' => $quote->payment_method,
            ]);

            foreach ($quote->lines as $line) {
                $lot = \App\Models\Lot::where('variant_id', $line->variant_id)
                    ->where(function($q) {
                        $q->whereNull('expiration_date')->orWhere('expiration_date', '>=', now());
                    })
                    ->orderBy('expiration_date', 'asc')
                    ->first();

                $snList = null;
                if ($lot) {
                    $units = \App\Models\StockUnit::where('lot_id', $lot->id)
                        ->where('status', '!=', 'vendue')
                        ->limit($line->quantity)
                        ->pluck('serial_number')
                        ->filter()
                        ->toArray();
                    if (!empty($units)) {
                        $snList = implode(', ', $units);
                    }
                }

                OrderLine::create([
                    'order_id' => $order->id,
                    'variant_id' => $line->variant_id,
                    'client_product_ref' => $line->client_product_ref,
                    'lot_number' => $lot?->lot_number,
                    'serial_numbers' => $snList,
                    'expiry_date' => $lot?->expiration_date,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unit_price,
                    'tax_rate' => $line->tax_rate ?? 20.00,
                    'discount_percent' => $line->discount_percent,
                ]);
            }

            \App\Models\Quote::destroy($quote->id);

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
            'quote_date' => 'nullable|date',
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
                'quote_date' => $request->quote_date ?? $quote->quote_date,
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
                    'tax_rate' => $line['tax_rate'] ?? $quote->lines->where('variant_id', $line['variant_id'])->first()->tax_rate ?? 20.00,
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
     * Maya: Suppression d'un devis (draft uniquement)
     */
    public function destroy(Quote $quote)
    {
        if (!in_array($quote->status, ['draft', 'À confirmer'])) {
            return response()->json(['message' => 'Seul un devis brouillon ou en attente peut être supprimé.'], 400);
        }

        DB::beginTransaction();
        try {
            $quote->lines()->forceDelete();
            $quote->forceDelete();
            DB::commit();
            return response()->json(['message' => 'Devis supprimé avec succès']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la suppression', 'error' => $e->getMessage()], 500);
        }
    }

    // =========================================================================
    // Maya: NOUVEAU WORKFLOW B2B — Devis Auto depuis la Vitrine
    // =========================================================================

    /**
     * Maya: Créer automatiquement un Client + un Devis depuis la vitrine publique.
     * Status: pending_approval (en attente de validation agent)
     */
    public function publicQuoteStore(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'client_type'  => 'required|in:company,individual',
            'client_name'  => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'required|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'cin'          => 'required_if:client_type,individual|nullable|string|max:50',
            'address'      => 'required|string|max:500',
            'city'         => 'required|string|max:100',
            'lines'        => 'required|array|min:1',
            'lines.*.variant_id' => 'required|integer|exists:product_variants,id',
            'lines.*.quantity'   => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // 1. Créer ou retrouver le client par email (clé unique)
            $client = \App\Models\Client::firstOrCreate(
                ['email' => $request->client_email],
                [
                    'type'         => $request->client_type,
                    'contact_name' => $request->client_name,
                    'company_name' => $request->company_name ?? '',
                    'phone'        => $request->client_phone,
                    'cin'          => $request->cin ?? '',
                    'ice'          => $request->ice ?? '',
                    'address'      => $request->address ?? '',
                    'city'         => $request->city ?? '',
                    'status'       => 'Prospect',
                    'source'       => 'web',
                ]
            );

            // 2. Créer le devis avec statut "en_attente_validation"
            $quote = Quote::create([
                'client_id' => $client->id,
                'status'    => 'pending_approval',
                'version'   => 1,
                'source'    => 'web',
            ]);

            // 3. Créer les lignes du devis avec le prix public de la variante
            foreach ($request->lines as $line) {
                $variant = \App\Models\ProductVariant::find($line['variant_id']);
                $unitPrice = $variant ? (float)$variant->public_price : 0;

                QuoteLine::create([
                    'quote_id'        => $quote->id,
                    'variant_id'      => $line['variant_id'],
                    'unit_price'      => $unitPrice,
                    'quantity'        => $line['quantity'],
                    'discount_percent' => 0,
                ]);
            }

            // 4. Envoyer email interne de notification
            try {
                \Illuminate\Support\Facades\Mail::to(env('MAIL_FROM_ADDRESS', 'info@kilatech.ma'))
                    ->send(new \App\Mail\NewQuoteRequestNotification($quote));
            } catch (\Exception $mailEx) {
                \Illuminate\Support\Facades\Log::warning('Maya: Notification email failed: ' . $mailEx->getMessage());
            }

            DB::commit();

            return response()->json([
                'success'      => true,
                'quote_number' => $quote->quote_number,
                'message'      => 'Votre demande a bien été reçue. Notre équipe va la valider et vous envoyer votre devis par email sous 24h.',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erreur lors de la création de votre demande.',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Maya: L'agent confirme et envoie le devis au client par email avec PDF.
     * Route protégée : auth:sanctum
     */
    public function confirmAndSend(Quote $quote)
    {
        if (!in_array($quote->status, ['pending_approval', 'draft'])) {
            return response()->json(['message' => 'Ce devis ne peut pas être envoyé dans son état actuel.'], 400);
        }

        try {
            // 1. Générer le PDF
            $pdf = $this->getQuotePdf($quote->load(['client', 'lines.variant.product']));
            $pdfContent = $pdf->output();

            // 2. Envoyer au client
            \Illuminate\Support\Facades\Mail::to($quote->client->email)
                ->send(new \App\Mail\QuoteSentToClient($quote, $pdfContent));

            // 3. Mettre à jour le statut du devis
            $quote->update(['status' => 'sent']);

            return response()->json([
                'success' => true,
                'message' => '✅ Le devis ' . $quote->quote_number . ' a été envoyé à ' . $quote->client->email,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'envoi du devis.',
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

}
