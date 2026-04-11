<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;

header('Content-Type: text/plain');

echo "--- Diagnostic Envoi Email KILATECH ---\n\n";

$to = request()->query('to') ?? 'info@kilatech.ma';

echo "Tentative d'envoi d'un email de test à : $to\n";
echo "Configuration actuelle :\n";
echo "Mailer : " . config('mail.default') . "\n";
echo "From : " . config('mail.from.address') . " (" . config('mail.from.name') . ")\n";

try {
    Mail::raw('Ceci est un email de test pour vérifier la configuration SMTP de Kilatech.', function ($message) use ($to) {
        $message->to($to)
                ->subject('Test Email Kilatech - ' . date('Y-m-d H:i:s'));
    });
    echo "\n✅ SUCCESS : L'email a été envoyé au driver de mail.\n";
    echo "Si vous ne le recevez pas, vérifiez les spams ou les logs de votre serveur SMTP.\n";
} catch (\Exception $e) {
    echo "\n❌ ERREUR : " . $e->getMessage() . "\n";
    echo "Fichier : " . $e->getFile() . " (Ligne " . $e->getLine() . ")\n";
}
