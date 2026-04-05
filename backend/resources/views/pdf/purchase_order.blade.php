<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Bon de Commande' }}</title>
    <style>
        @page { margin: 40px 40px 80px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; line-height: 1.5; }
        
        .header-table { width: 100%; border-bottom: 2px solid #003366; margin-bottom: 30px; padding-bottom: 10px; }
        .logo-box { width: 50%; vertical-align: middle; }
        .logo-box img { max-width: 240px; height: auto; }
        .company-brief { width: 50%; text-align: right; vertical-align: middle; font-size: 9px; color: #475569; }
        .company-brief strong { font-size: 14px; color: #003366; display: block; margin-bottom: 3px; letter-spacing: -0.5px; }

        .grid-table { width: 100%; margin-bottom: 30px; }
        .grid-table td { vertical-align: top; width: 50%; }
        .recipient-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; }
        .recipient-box .tag { font-weight: 800; color: #64748b; text-transform: uppercase; font-size: 8px; letter-spacing: 1px; margin-bottom: 8px; }
        .recipient-box .customer-name { font-size: 14px; font-weight: 900; color: #0f172a; margin-bottom: 8px; }
        
        .doc-identity { text-align: right; padding: 0 10px; }
        .type-banner { font-size: 22px; font-weight: 900; color: #003366; margin-bottom: 5px; }
        .id-line { font-size: 12px; font-weight: bold; color: #475569; }

        .content-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .content-table th { background-color: #003366; color: #ffffff; padding: 12px 10px; text-align: left; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        .content-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; }
        .content-table tr:nth-child(even) { background-color: #fcfdfe; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .financial-grid { width: 100%; }
        .financial-grid td { vertical-align: top; }
        .notes-and-payment { width: 55%; padding-right: 40px; }
        .notes-and-payment strong { color: #003366; }
        
        .totals-widget { width: 45%; background-color: #f8fafc; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; }
        .total-row { width: 100%; border-collapse: collapse; }
        .total-row td { padding: 4px 0; font-size: 10px; }
        .grand-total-row { border-top: 2px solid #003366; margin-top: 10px; padding-top: 10px; font-size: 13px; font-weight: 900; color: #003366; }

        .footer-polished { position: fixed; bottom: -40px; left: 0; right: 0; width: 100%; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 8px; color: #64748b; text-align: center; line-height: 1.6; }
        
        .signature-area { margin-top: 60px; width: 100%; }
        .signature-area td { width: 50%; padding: 20px; text-align: center; border-top: 1px dashed #cbd5e1; font-weight: bold; color: #64748b; font-size: 9px; }

    </style>
</head>
<body>

    @php
        $logoSrc = null;
        try {
            $logoPath = public_path('images/logo.png');
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoSrc = 'data:image/png;base64,' . $logoData;
            }
        } catch (\Exception $e) {}
    @endphp

    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if($logoSrc) <img src="{{ $logoSrc }}" alt="Logo"> @else <h2 style="color: #003366; margin: 0;">KILATECH</h2> @endif
            </td>
            <td class="company-brief">
                <strong>SARLAU KILATECH</strong>
                Dispositifs Médicaux & Équipements<br>
                5 RUE ENNOUSSOUR, RDC C/O CASABLANCA<br>
                Email : info@kilatech.ma | Tél : 05 22 72 66 73
            </td>
        </tr>
    </table>

    <table class="grid-table">
        <tr>
            <td>
                <div class="recipient-box">
                    <div class="tag">Informations Fournisseur</div>
                    <div class="customer-name">{{ $client_name }}</div>
                    @if(!empty($client_ice)) <div><strong>ICE:</strong> {{ $client_ice }}</div> @endif
                    <div>{{ $client_address ?? '-' }}</div>
                    <div>{{ $client_city ?? 'Casablanca' }}</div>
                </div>
            </td>
            <td>
                <div class="doc-identity">
                    <div class="type-banner">{{ $document_type }}</div>
                    <div class="id-line">Réf : {{ $document_number }}</div>
                    <div style="margin-top: 10px;">Date : {{ \Carbon\Carbon::parse($document_date)->format('d/m/Y') }}</div>
                    <div style="font-size: 9px; color: #003366; font-weight: bold; margin-top: 5px;">Livraison prévue le : {{ !empty($delivery_at) ? \Carbon\Carbon::parse($delivery_at)->format('d/m/Y') : 'À confirmer' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="content-table">
        <thead>
            <tr>
                <th style="width: 10%;">RÉF.</th>
                <th style="width: 50%;">DÉSIGNATION</th>
                <th class="text-center" style="width: 10%;">UN</th>
                <th class="text-center" style="width: 10%;">QTE</th>
                <th class="text-right" style="width: 20%;">P.U HT (MAD)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td><strong>{{ $item['reference'] }}</strong></td>
                <td>
                    <div style="font-weight: 800; color: #0f172a;">{{ $item['name'] }}</div>
                    @if(!empty($item['description'])) <div style="font-size: 7px; color: #64748b;">{{ $item['description'] }}</div> @endif
                </td>
                <td class="text-center">{{ $item['unit'] ?? 'U' }}</td>
                <td class="text-center">{{ number_format($item['quantity'], 2, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($item['unit_price_ht'], 2, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="financial-grid">
        <tr>
            <td class="notes-and-payment">
                <div style="font-size: 9px;">
                    <strong>Mode de Règlement :</strong> {{ $payment_method }}<br>
                    <strong>Échéance :</strong> {{ $validity_date }}<br>
                </div>
                <div style="font-weight: bold; color: #003366; margin-top: 20px;">
                    Arrêté le présent bon de commande à la somme de :<br>
                    <span style="font-size: 10px; text-transform: uppercase;">{{ $amount_in_words }}</span>
                </div>
            </td>
            <td>
                <div class="totals-widget">
                    <table class="total-row">
                        <tr>
                            <td>TOTAL HT</td>
                            <td class="text-right">{{ number_format($total_ht, 2, ',', ' ') }} MAD</td>
                        </tr>
                        @foreach($tva_breakdown as $rate => $val)
                        <tr>
                            <td>TVA ({{ $rate }}%)</td>
                            <td class="text-right">{{ number_format($val['tva'], 2, ',', ' ') }} MAD</td>
                        </tr>
                        @endforeach
                        <tr class="grand-total-row">
                            <td>NET À PAYER TTC</td>
                            <td class="text-right">{{ number_format($total_ttc, 2, ',', ' ') }} MAD</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="signature-area">
        <tr>
            <td>Cachet & Signature Fournisseur</td>
            <td>Visa Responsable Achats (KILATECH)</td>
        </tr>
    </table>

    <div class="footer-polished">
        KILATECH SARLAU | CAPITAL 100 000 DH | RC 657473 | IF 66194148 | ICE 003652842000053<br>
        Siège Social : 5 RUE ENNOUSSOUR, RDC C/O CASABLANCA, MAROC | Email : info@kilatech.ma
    </div>

</body>
</html>
