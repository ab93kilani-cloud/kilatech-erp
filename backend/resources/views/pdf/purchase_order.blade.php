<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Bon de Commande' }}</title>
    <style>
        @page { margin: 40px 40px 80px 40px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 10px; color: #1e293b; margin: 0; padding: 0; line-height: 1.6; }
        
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
        .text-center { text-align: center; }

        .logistics-notes { border-top: 2px solid #003366; padding-top: 20px; font-size: 10px; color: #1e293b; }
        .footer-polished { position: fixed; bottom: -40px; left: 0; right: 0; width: 100%; border-top: 1px solid #e2e8f0; padding-top: 10px; font-size: 8px; color: #64748b; text-align: center; line-height: 1.6; }
        
        .signature-area { margin-top: 60px; width: 100%; }
        .signature-area td { width: 50%; padding: 40px 20px; text-align: center; border: 1px dashed #cbd5e1; font-weight: bold; color: #64748b; font-size: 9px; background-color: #f8fafc; border-radius: 8px; }

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
                    <div class="tag">Destinataire (Fournisseur)</div>
                    <div class="customer-name">{{ $client_name }}</div>
                    @if(!empty($client_ice)) <div><strong>ICE:</strong> {{ $client_ice }}</div> @endif
                    <div>{{ $client_address ?? '-' }}</div>
                    <div>{{ $client_city ?? 'Casablanca' }}</div>
                </div>
            </td>
            <td>
                <div class="doc-identity">
                    <div class="type-banner">{{ $document_type }}</div>
                    <div class="id-line">BC N° : {{ $document_number }}</div>
                    <div style="margin-top: 10px; font-weight: bold;">Date d'émission : {{ \Carbon\Carbon::parse($document_date)->format('d/m/Y') }}</div>
                    <div style="font-size: 11px; color: #003366; font-weight: 900; margin-top: 15px; background: #eff6ff; display: inline-block; padding: 5px 10px; border-radius: 4px;">
                        LIVRAISON PRÉVUE LE : {{ !empty($delivery_at) ? \Carbon\Carbon::parse($delivery_at)->format('d/m/Y') : 'À CONFIRMER' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="content-table">
        <thead>
            <tr>
                <th style="width: 15%;">RÉFÉRENCE / SKU</th>
                <th style="width: 60%;">DÉSIGNATION DES ARTICLES</th>
                <th class="text-center" style="width: 10%;">UNITÉ</th>
                <th class="text-center" style="width: 15%;">QUANTITÉ À LIVRER</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td><strong>{{ $item['reference'] }}</strong></td>
                <td>
                    <div style="font-weight: 800; color: #0f172a; font-size: 11px;">{{ $item['name'] }}</div>
                    @if(!empty($item['description'])) <div style="font-size: 8px; color: #64748b;">{{ $item['description'] }}</div> @endif
                </td>
                <td class="text-center" style="font-size: 11px; font-weight: bold;">{{ $item['unit'] ?? 'U' }}</td>
                <td class="text-center" style="font-size: 13px; font-weight: 900; color: #003366;">{{ number_format($item['quantity'], 0, ',', ' ') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="logistics-notes">
        <strong>Notes Logistiques & Règlement :</strong><br>
        • Mode de paiement prévu : {{ $payment_method }}<br>
        • Échéance de règlement : {{ $validity_date }}<br>
        <div style="margin-top: 10px; font-size: 9px; color: #64748b;">
            Veuillez accuser réception de ce bon de commande et nous confirmer les quantités disponibles ainsi que la date exacte de livraison.
        </div>
    </div>

    <table class="signature-area">
        <tr>
            <td>
                Visa Responsable Achats (KILATECH)<br>
                <div style="margin-top: 15px; font-size: 8px; font-weight: normal;">(Bon pour accord)</div>
            </td>
            <td>
                Accusé de réception & Cachet Fournisseur<br>
                <div style="margin-top: 15px; font-size: 8px; font-weight: normal;">(Confirmé le ..... / ..... / 202...)</div>
            </td>
        </tr>
    </table>

    <div class="footer-polished">
        KILATECH SARLAU | CAPITAL 100 000 DH | RC 657473 | IF 66194148 | ICE 003652842000053<br>
        Siège Social : 5 RUE ENNOUSSOUR, RDC C/O CASABLANCA, MAROC
    </div>

</body>
</html>
