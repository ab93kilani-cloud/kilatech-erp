<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;

echo "--- VERIFICATION DU FOOTER SEQUENCE ---\n";

$data = [
    'document_type' => 'FACTURE',
    'document_number' => 'FA-2603-0999',
    'document_date' => now(),
    'client_name' => 'KILATECH PRO SERVICES',
    'client_ice' => '00321456987',
    'client_address' => 'Boulevard Hassan II',
    'client_city' => 'Casablanca',
    'items' => [
        [
            'reference' => 'KILA-MED-X',
            'name' => 'Moniteur de Signes Vitaux High-End',
            'description' => 'Test de rendu du nouveau pied de page poli.',
            'quantity' => 1,
            'unit_price_ht' => 12500,
            'total_ht' => 12500
        ]
    ],
    'total_ht' => 12500,
    'total_tva' => 2500,
    'total_ttc' => 15000,
    'balance_due' => 15000,
    'payment_method' => 'Virement (RIB en bas de page)',
    'title' => 'Verif_Footer_Kilatech'
];

$pdf = Pdf::loadView('pdf.document', $data);
$pdf->save('verif_footer_sequence.pdf');
echo "Document genere : verif_footer_sequence.pdf\n";
