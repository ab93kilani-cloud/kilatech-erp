<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Order;
use App\Models\OrderLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                'description' => '', // Selon besoin
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
            'client_name' => $quote->client->company_name ?? $quote->client->contact_name,
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
            'title' => 'Devis ' . $quote->quote_number
        ];

        $pdf = Pdf::loadView('pdf.document', $data);
        return $pdf->download($quote->quote_number . '.pdf');
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
        if ($quote->status !== 'draft') {
            return response()->json(['message' => 'Seul un devis brouillon peut être modifié.'], 400);
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
        if ($quote->status !== 'draft') {
            return response()->json(['message' => 'Seul un devis brouillon peut être supprimé.'], 400);
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

    /**
     * Store a quote request from the public vitrine (guest or new client)
     */
    public function publicStore(Request $request)
    {
        $request->validate([
            'client_type' => 'required|in:company,individual',
            'client_name' => 'required|string|max:255',
            'client_email' => 'required|email|max:255',
            'client_phone' => 'required|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'ice' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.variant_id' => 'required|exists:product_variants,id',
            'lines.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            // Find or create a temporary client
            $client = \App\Models\Client::firstOrCreate(
                ['email' => $request->client_email],
                [
                    'type' => $request->client_type,
                    'company_name' => $request->client_type === 'company' ? ($request->company_name ?? $request->client_name) : null,
                    'contact_name' => $request->client_name,
                    'phone' => $request->client_phone,
                    'address' => $request->address,
                    'city' => $request->city,
                    'ice' => $request->client_type === 'company' ? $request->ice : null,
                    'status' => 'suspect',
                ]
            );

            // If client exists, update their info
            if (!$client->wasRecentlyCreated) {
                $client->update([
                    'type' => $request->client_type,
                    'company_name' => $request->client_type === 'company' ? ($request->company_name ?? $request->client_name) : $client->company_name,
                    'contact_name' => $request->client_name,
                    'phone' => $request->client_phone,
                    'address' => $request->address ?? $client->address,
                    'city' => $request->city ?? $client->city,
                    'ice' => $request->client_type === 'company' ? ($request->ice ?? $client->ice) : $client->ice,
                ]);
            }

            // Create the quote
            $quote = Quote::create([
                'client_id' => $client->id,
                'status' => 'À confirmer',
                'version' => 1,
                'valid_until' => now()->addDays(15)
            ]);

            foreach ($request->lines as $line) {
                $variant = \App\Models\ProductVariant::find($line['variant_id']);
                $ttcPrice = $variant->public_price ?? 0;
                $htPrice = round($ttcPrice / 1.2, 2); // Convert to HT if catalogue is TTC
                
                QuoteLine::create([
                    'quote_id' => $quote->id,
                    'variant_id' => $line['variant_id'],
                    'unit_price' => $htPrice,
                    'quantity' => $line['quantity'],
                    'discount_percent' => 0,
                ]);
            }

            // NOTIFICATION FOR COMMERCIALS & ADMINS
            $staff = \App\Models\User::whereHas('role', function ($q) {
                $q->whereIn('name', ['Admin', 'Gérant', 'Commercial']);
            })->get();

            foreach ($staff as $user) {
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'type' => 'quote_request',
                    'title' => 'Nouvelle demande de devis vitrine',
                    'message' => 'Le client ' . $request->client_name . ' a généré un devis automatique pour ' . count($request->lines) . ' produits.',
                    'action_url' => '/admin/sales/quotes/' . $quote->id,
                    'is_read' => false
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Devis généré avec succès',
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la génération du devis', 'error' => $e->getMessage()], 500);
        }
    }
}
