<?php

namespace App\Mail;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PublicQuoteRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;

    public function __construct(Quote $quote)
    {
        $this->quote = $quote->load(['client', 'lines.variant.product']);
    }

    public function build()
    {
        $pdf = $this->generatePdfContent();

        return $this->subject('Votre Devis Kilatech : ' . $this->quote->quote_number)
                    ->view('emails.public_quote')
                    ->attachData($pdf->output(), 'Devis_' . $this->quote->quote_number . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
    }

    protected function generatePdfContent()
    {
        $quote = $this->quote;

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
            'client_name' => $quote->client->company_name ?? $quote->client->contact_name,
            'client_ice' => $quote->client->ice,
            'client_address' => $quote->client->address,
            'client_city' => $quote->client->city,
            'items' => $items,
            'total_ht' => $totalHT,
            'total_tva' => $rawTTC - $totalHT,
            'discount_amount' => $discountAmount,
            'total_ttc' => $totalTTC,
            'payment_method' => 'Virement / Chèque',
            'validity_date' => $quote->valid_until,
            'balance_due' => $totalTTC,
            'title' => 'Devis ' . $quote->quote_number
        ];

        return Pdf::loadView('pdf.document', $data);
    }
}
