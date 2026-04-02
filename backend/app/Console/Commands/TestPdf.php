<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CreditNote;
use Barryvdh\DomPDF\Facade\Pdf;

class TestPdf extends Command
{
    protected $signature = 'test:pdf';
    protected $description = 'Test PDF Generation';

    public function handle()
    {
        try {
            $creditNote = CreditNote::with(['client', 'invoice.order.lines.variant'])->first();
            
            if (!$creditNote) {
                $this->error("Aucun avoir trouvé.");
                return;
            }

            $items = collect([]);
            $totalHT = 0;
            $totalTTC = 0;
            $totalTVA = 0;

            if ($creditNote->invoice && $creditNote->invoice->order && $creditNote->invoice->order->lines) {
                $items = $creditNote->invoice->order->lines->map(function ($line) {
                    $ht = $line->unit_price * $line->quantity;
                    $tvaRate = $line->variant->tva_rate ?? 20;
                    $ttc = $ht * (1 + ($tvaRate / 100));

                    return [
                        'reference' => $line->variant->sku ?? 'N/A',
                        'name' => $line->variant->name_variation ?? 'Produit',
                        'description' => 'Retour Avoir',
                        'quantity' => $line->quantity,
                        'unit_price_ht' => $line->unit_price,
                        'unit_price_ttc' => $ttc,
                        'total_ht' => $ht,
                        'total_ttc' => $ttc,
                        'tva' => $tvaRate
                    ];
                });
                $totalHT = $items->sum('total_ht');
                $totalTTC = $items->sum('total_ttc');
                $totalTVA = $totalTTC - $totalHT; 
            } else {
                $totalTTC = $creditNote->total_amount;
                $totalHT = $totalTTC / 1.20;
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
            $output = $pdf->output(); // Generate the pdf content string
            
            $this->info("PDF Generated successfully! Size: " . strlen($output) . " bytes");
            
            file_put_contents('test.pdf', $output);
            $this->info("Saved to test.pdf");
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
}
