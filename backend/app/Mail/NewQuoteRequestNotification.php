<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Quote;

/**
 * Maya: Email interne envoyé à l'agent quand un client soumet une demande de devis.
 */
class NewQuoteRequestNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function build()
    {
        $quote = $this->quote->load(['client', 'lines.variant.product']);
        $client = $quote->client;
        $clientName = $client->type === 'company'
            ? ($client->company_name . ' — ' . $client->contact_name)
            : $client->contact_name;

        return $this
            ->subject('📥 Nouvelle Demande de Devis — ' . $quote->quote_number)
            ->html(
                '<h2>Nouvelle Demande de Devis</h2>' .
                '<p><strong>Numéro :</strong> ' . $quote->quote_number . '</p>' .
                '<p><strong>Client :</strong> ' . htmlspecialchars($clientName) . '</p>' .
                '<p><strong>Email :</strong> ' . htmlspecialchars($client->email) . '</p>' .
                '<p><strong>Téléphone :</strong> ' . htmlspecialchars($client->phone ?? 'N/A') . '</p>' .
                '<p><strong>Nombre de lignes :</strong> ' . $quote->lines->count() . '</p>' .
                '<hr><p>Connectez-vous au dashboard pour valider et envoyer ce devis au client.</p>'
            );
    }
}
