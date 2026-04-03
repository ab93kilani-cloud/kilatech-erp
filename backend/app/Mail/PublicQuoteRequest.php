<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PublicQuoteRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;
    public $pdfContent;

    public function __construct($quote, $pdfContent)
    {
        $this->quote = $quote;
        $this->pdfContent = $pdfContent;
    }

    public function build()
    {
        return $this->subject('Votre Devis Kilatech : ' . $this->quote->quote_number)
                    ->view('emails.public_quote')
                    ->attachData($this->pdfContent, 'Devis_' . $this->quote->quote_number . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
    }
}
