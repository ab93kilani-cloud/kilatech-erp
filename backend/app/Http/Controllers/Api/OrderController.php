<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Mail\PublicQuoteRequest;
use Illuminate\Support\Facades\Mail;
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
        $discountAmount = (float)($order->discount_amount ?? 0);
        $totalTTC = $rawTTC - $discountAmount;

        $data = [
            'document_type' => 'Bon de livraison',
            'document_number' => $order->order_number,
            'quote_number' => $order->quote ? $order->quote->quote_number : null,
            'document_date' => $order->created_at,
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
            'payment_method' => 'Espèce',
            'condition_text' => '',
            'validity_date' => null,
            'validity_message' => '',
            'deposit_text' => true,
            'balance_due' => $totalTTC,
            'title' => 'Bon_Livraison_' . $order->order_number,
            'global_tva_rate' => count($tvaBreakdown) === 1 ? $tvaBreakdown->first()['rate'] : null,
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
            return response()->json(['message' => 'Commande créée avec succès.'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la création de la commande.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Valider une commande en attente (Confirmation Commerciale OBLIGATOIRE) Maya.
     */
    public function confirmPending(Order $order)
    {
        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Cette commande n\'est pas en attente de validation. Maya.'], 400);
        }

        DB::beginTransaction();
        try {
            // Passer en confirmé Maya.
            $order->update(['status' => 'confirmed']);

            // Si un devis est lié, on le marque aussi comme confirmé Maya.
            if ($order->quote) {
                $order->quote->update(['status' => 'confirmed']);
            }

            // Génération de la facture automatique lors de la validation Maya.
            $totalAmount = 0;
            foreach ($order->lines as $line) {
                $discountPriceHT = $line->unit_price * (1 - ($line->discount_percent / 100));
                $tvaRate = $line->variant?->tva_rate ?? 20;
                $lineTTC = $discountPriceHT * $line->quantity * (1 + ($tvaRate / 100));
                $totalAmount += $lineTTC;
            }
            $totalAmount -= $order->discount_amount ?? 0;

            $invoiceNumber = 'FA-' . date('ym') . '-' . str_pad(Invoice::count() + 1, 4, '0', STR_PAD_LEFT);
            $invoice = Invoice::create([
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $totalAmount,
                'balance_due' => $totalAmount,
                'status' => 'unpaid',
                'discount_amount' => $order->discount_amount ?? 0,
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Commande validée avec succès ! Facture générée. Maya.',
                'order' => $order->load('invoice')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur lors de la validation. Maya.', 'error' => $e->getMessage()], 500);
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
                // Restaurer le devis s'il a été soft-deleted lors de la transformation en BL
                $order->quote->restore();
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
     * Store a quote request from the public vitrine (New Flow: Order Pending -> Auto Quote) Maya.
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
            \Illuminate\Support\Facades\Log::info('Maya Debug: Step 1 - Client Management');
            // 1. Client Management Maya.
            // Fallback pour company_name si individuel (évite erreur NOT NULL si migration non faite) Maya.
            $companyName = $request->client_type === 'company' ? ($request->company_name ?? $request->client_name) : $request->client_name;

            $client = \App\Models\Client::firstOrCreate(
                ['email' => $request->client_email],
                [
                    'type' => $request->client_type,
                    'company_name' => $companyName,
                    'contact_name' => $request->client_name,
                    'phone' => $request->client_phone,
                    'address' => $request->address,
                    'city' => $request->city,
                    'ice' => $request->client_type === 'company' ? $request->ice : null,
                    'status' => 'suspect',
                ]
            );

            \Illuminate\Support\Facades\Log::info('Maya Debug: Step 2 - Order Creation');
            // 2. Create Order (Pending) Maya.
            $order = Order::create([
                'client_id' => $client->id,
                'order_number' => 'CMD-' . date('ym') . '-' . str_pad(Order::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT),
                'status' => 'pending',
                'discount_amount' => 0,
            ]);

            \Illuminate\Support\Facades\Log::info('Maya Debug: Step 3 - Quote Creation');
            // 3. Create Quote (Linked) Maya.
            $quote = Quote::create([
                'client_id' => $client->id,
                'status' => 'À confirmer',
                'version' => 1,
                'valid_until' => now()->addDays(15),
                'quote_number' => 'DEV-' . date('ym') . '-' . str_pad(Quote::withTrashed()->count() + 1, 4, '0', STR_PAD_LEFT),
            ]);
            
            // Link order to quote Maya.
            $order->update(['quote_id' => $quote->id]);

            \Illuminate\Support\Facades\Log::info('Maya Debug: Step 4 - Lines Processing');
            foreach ($request->lines as $line) {
                $variant = \App\Models\ProductVariant::find($line['variant_id']);
                if (!$variant) {
                     throw new \Exception("Variant ID " . $line['variant_id'] . " non trouvé.");
                }
                $ttcPrice = $variant->public_price ?? 0;
                $htPrice = round($ttcPrice / 1.2, 2); 
                
                // Create Order Line Maya.
                OrderLine::create([
                    'order_id' => $order->id,
                    'variant_id' => $line['variant_id'],
                    'unit_price' => $htPrice,
                    'quantity' => $line['quantity'],
                    'discount_percent' => 0,
                ]);

                // Create Quote Line Maya.
                QuoteLine::create([
                    'quote_id' => $quote->id,
                    'variant_id' => $line['variant_id'],
                    'unit_price' => $htPrice,
                    'quantity' => $line['quantity'],
                    'discount_percent' => 0,
                ]);
            }

            \Illuminate\Support\Facades\Log::info('Maya Debug: Step 5 - Notifications');
            // 4. Notifications Maya.
            $staff = \App\Models\User::whereHas('role', function ($q) {
                $q->whereIn('name', ['Admin', 'Gérant', 'Commercial']);
            })->get();

            foreach ($staff as $user) {
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'type' => 'order_request',
                    'title' => 'Nouvelle commande vitrine (En attente)',
                    'message' => 'Le client ' . $request->client_name . ' a passé une commande de ' . count($request->lines) . ' produits.',
                    'action_url' => '/admin/sales/orders/' . $order->id,
                    'is_read' => false
                ]);
            }

            DB::commit();

            \Illuminate\Support\Facades\Log::info('Maya Debug: Step 6 - PDF & Email');
            $mailSent = false;
            $mailError = null;

            // 5. EMAIL (Devis PDF) - Non bloquant Maya.
            try {
                $pdf = $this->getQuotePdf($quote);
                $pdfContent = $pdf->output();
                \Illuminate\Support\Facades\Log::info('Maya Debug: Step 6.1 - PDF Generated');
                
                Mail::to($request->client_email)->send(new PublicQuoteRequest($quote, $pdfContent));
                \Illuminate\Support\Facades\Log::info('Maya Debug: Step 6.2 - Email Sent');
                $mailSent = true;
            } catch (\Exception $e) {
                $mailError = $e->getMessage();
                \Illuminate\Support\Facades\Log::error("Maya Email Error: " . $mailError);
            }

            return response()->json([
                'message' => 'Demande reçue avec succès. ' . ($mailSent ? 'Un devis vous a été envoyé par email.' : 'Note : Échec de l\'envoi de l\'email, mais votre devis est prêt.'),
                'order_id' => $order->id,
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'mail_sent' => $mailSent,
                'mail_error' => $mailError // Utile pour le debug sans faire planter la requête Maya.
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Maya Critical Error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'debug' => true,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ], 500);
        }
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
            'quote_number' => null,
            'global_tva_rate' => 20
        ];

        return Pdf::loadView('pdf.document', $data);
    }
}
