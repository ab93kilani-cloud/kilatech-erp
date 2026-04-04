<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Quote;

/**
 * Maya: Email envoyé au client après confirmation de l'agent.
 * Contient le PDF du devis en pièce jointe.
 */
class QuoteSentToClient extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote, public string $pdfContent) {}

    public function build()
    {
        $quote = $this->quote->load('client');
        $clientName = $quote->client->type === 'company'
            ? $quote->client->company_name
            : $quote->client->contact_name;

        return $this
            ->subject('Votre Devis Kilatech — ' . $quote->quote_number)
            ->html(
                '<p>Bonjour ' . htmlspecialchars($clientName) . ',</p>' .
                '<p>Votre devis <strong>' . $quote->quote_number . '</strong> a été validé par notre équipe.</p>' .
                '<p>Vous trouverez votre devis en pièce jointe au format PDF.</p>' .
                '<p>Pour toute question, contactez-nous à <a href="mailto:info@kilatech.ma">info@kilatech.ma</a>.</p>' .
                '<br><p>Cordialement,<br><strong>L\'équipe KILATECH</strong></p>'
            )
            ->attachData($this->pdfContent, $quote->quote_number . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
