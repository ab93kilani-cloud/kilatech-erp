<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        @page {
            margin: 40px 40px 80px 40px;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }

        /* En-tête de Prestige */
        .header-table {
            width: 100%;
            border-bottom: 2px solid #003366;
            margin-bottom: 30px;
            padding-bottom: 10px;
        }
        .logo-box {
            width: 50%;
            vertical-align: middle;
        }
        .logo-box img {
            max-width: 240px;
            height: auto;
        }
        .company-brief {
            width: 50%;
            text-align: right;
            vertical-align: middle;
            font-size: 9px;
            color: #475569;
        }
        .company-brief strong {
            font-size: 14px;
            color: #003366;
            display: block;
            margin-bottom: 3px;
            letter-spacing: -0.5px;
        }

        /* Grille d'information */
        .grid-table {
            width: 100%;
            margin-bottom: 30px;
        }
        .grid-table td {
            vertical-align: top;
            width: 50%;
        }
        .recipient-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
        }
        .recipient-box .tag {
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .recipient-box .customer-name {
            font-size: 14px;
            font-weight: 900;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .doc-identity {
            text-align: right;
            padding: 0 10px;
        }
        .type-banner {
            font-size: 22px;
            font-weight: 900;
            color: #003366;
            margin-bottom: 5px;
        }
        .id-line {
            font-size: 12px;
            font-weight: bold;
            color: #475569;
        }

        /* Tableau d'Articles */
        .content-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        .content-table th {
            background-color: #003366;
            color: #ffffff;
            padding: 12px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }
        .content-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .content-table tr:nth-child(even) {
            background-color: #fcfdfe;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Bloc Financier */
        .financial-grid {
            width: 100%;
        }
        .financial-grid td { vertical-align: top; }
        .notes-and-payment {
            width: 55%;
            padding-right: 40px;
        }
        .notes-and-payment strong { color: #003366; }
        
        .totals-widget {
            width: 45%;
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e2e8f0;
        }
        .total-row {
            width: 100%;
            border-collapse: collapse;
        }
        .total-row td {
            padding: 4px 0;
            font-size: 10px;
        }
        .grand-total-row {
            border-top: 2px solid #003366;
            margin-top: 10px;
            padding-top: 10px;
            font-size: 13px;
            font-weight: 900;
            color: #003366;
        }

        /* Footer 3 Colonnes Structuré */
        .footer-polished {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            width: 100%;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 8px;
            color: #64748b;
            text-align: center;
            line-height: 1.6;
        }

        .page-counter {
            text-align: right;
            font-weight: bold;
            margin-top: 5px;
        }
        .page-counter:after { content: "Page " counter(page); }

    </style>
</head>
<body>

    @foreach($copies ?? [null] as $copyLabel)
    
    @if(!$loop->first)
    <div style="page-break-before: always;"></div>
    @endif

    @php
        $logoSrc = null;
        try {
            $logoPath = public_path('images/logo.png');
            if (file_exists($logoPath)) {
                $logoData = base64_encode(file_get_contents($logoPath));
                $logoSrc = 'data:image/png;base64,' . $logoData;
            }
        } catch (\Exception $e) {
            $logoSrc = null;
        }
    @endphp

    <table class="header-table">
        <tr>
            <td class="logo-box">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Kilatech Logo">
                @else
                    <h2 style="color: #003366; margin: 0;">KILATECH</h2>
                @endif
            </td>
            <td class="company-brief">
                <strong>SARLAU KILATECH</strong>
                Spécialiste en Dispositifs Médicaux & Équipements<br>
                5 RUE ENNOUSSOUR, RDC C/O CASABLANCA<br>
                Maroc
            </td>
        </tr>
    </table>

    <table class="grid-table">
        <tr>
            <td>
                <div class="recipient-box">
                    <div class="tag">Informations Client</div>
                    <div class="customer-name">{{ $client_name }}</div>
                    @if(!empty($client_contact) && $client_contact !== $client_name)
                    <div style="font-size: 9px; color: #64748b; margin-bottom: 5px;">Contact: {{ $client_contact }}</div>
                    @endif
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
                    
                    @if(!empty($validity_date))
                    <div style="font-weight: bold; color: #dc2626; margin-top: 5px;">Validité : Jusqu'au {{ \Carbon\Carbon::parse($validity_date)->format('d/m/Y') }}</div>
                    @endif

                    @if(!empty($quote_number))
                    <div style="font-size: 9px; color: #64748b; margin-top: 5px;">Réf. Devis d'origine : {{ $quote_number }}</div>
                    @endif
                    @if(!empty($order_number))
                    <div style="font-size: 9px; color: #64748b; margin-top: 5px;">Réf. BL d'origine : {{ $order_number }}</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <table class="content-table">
        <thead>
            <tr>
                <th style="width: 8%; font-size: 7px;">REF.</th>
                <th style="width: 26%; font-size: 7px;">DÉSIGNATION</th>
                <th class="text-center" style="width: 5%; font-size: 7px;">UN</th>
                <th class="text-center" style="width: 6%; font-size: 7px;">QTE</th>
                <th class="text-right" style="width: 9%; font-size: 7px;">PU HT</th>
                <th class="text-right" style="width: 9%; font-size: 7px;">PU TTC</th>
                <th class="text-center" style="width: 6%; font-size: 7px;">REM.</th>
                <th class="text-right" style="width: 9%; font-size: 7px;">T.HT</th>
                <th class="text-right" style="width: 9%; font-size: 7px;">T.TTC</th>
                <th class="text-center" style="width: 7%; font-size: 7px;">TAXE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td style="font-size: 8px;">{{ $item['reference'] }}</td>
                <td>
                    <div style="font-weight: 800; color: #0f172a; font-size: 9px;">{{ $item['name'] }}</div>
                    
                    @if(!empty($item['client_product_ref']))
                    <div style="font-size: 7px; color: #64748b; margin-top: 2px;"><strong>Réf Client :</strong> {{ $item['client_product_ref'] }}</div>
                    @endif

                    @if(!empty($item['lot_number']))
                    <div style="font-size: 7px; color: #64748b; margin-top: 1px;"><strong>Lot :</strong> {{ $item['lot_number'] }} @if(!empty($item['expiry_date'])) (Exp: {{ \Carbon\Carbon::parse($item['expiry_date'])->format('d/m/Y') }}) @endif</div>
                    @endif

                    @if(!empty($item['serial_numbers']))
                    <div style="font-size: 7px; color: #64748b; margin-top: 1px;"><strong>S/N :</strong> {{ $item['serial_numbers'] }}</div>
                    @endif

                    @if(!empty($item['description']))
                    <div style="font-size: 7px; color: #64748b; margin-top: 1px;">{{ $item['description'] }}</div>
                    @endif
                </td>
                <td class="text-center" style="font-size: 8px;">{{ $item['unit'] ?? 'U' }}</td>
                <td class="text-center" style="font-size: 8px;">{{ number_format($item['quantity'], 2, ',', ' ') }}</td>
                <td class="text-right" style="font-size: 8px;">{{ number_format($item['unit_price_ht'], 2, ',', ' ') }}</td>
                <td class="text-right" style="font-size: 8px;">{{ number_format($item['unit_price_ttc'], 2, ',', ' ') }}</td>
                <td class="text-center" style="font-size: 8px;">{{ $item['discount'] }}</td>
                <td class="text-right" style="font-size: 8px; font-weight: 700;">{{ number_format($item['total_ht'], 2, ',', ' ') }}</td>
                <td class="text-right" style="font-size: 8px; font-weight: 800;">{{ number_format($item['total_ttc'], 2, ',', ' ') }}</td>
                <td class="text-center" style="font-size: 8px;">{{ $item['tva'] }}%</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="financial-grid">
        <tr>
            <td class="notes-and-payment">
                @if($document_type === 'BON DE LIVRAISON')
                <div style="background-color: #f1f5f9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="color: #003366; font-weight: bold; text-transform: uppercase; font-size: 8px; margin-bottom: 10px;">Accusé de réception</div>
                    <div style="height: 50px; border: 1px dashed #cbd5e1; background: white;"></div>
                </div>
                @endif

                <div style="font-size: 9px;">
                    <strong>Mode de Règlement :</strong> {{ $payment_method ?? 'Virement ou Chèque' }}<br>
                    @if(!empty($validity_date))
                    <strong>Validité :</strong> Jusqu'au {{ \Carbon\Carbon::parse($validity_date)->format('d/m/Y') }}
                    @endif
                </div>

                <div style="font-weight: bold; color: #003366; margin-top: 15px;">
                    Arrêté le présent document à la somme de :<br>
                    <span style="font-size: 10px; text-transform: uppercase;">{{ \App\Helpers\NumberToWords::convert($balance_due ?? $total_ttc) }}</span>
                </div>
            </td>
            <td>
                <div class="totals-widget">
                    <table class="total-row">
                        <tr>
                            <td>TOTAL HT</td>
                            <td class="text-right">{{ number_format($total_ht, 2, ',', ' ') }} MAD</td>
                        </tr>
                        
                        @if(isset($tva_breakdown))
                            @foreach($tva_breakdown as $rate => $values)
                                <tr>
                                    <td>TVA ({{ $rate }}%)</td>
                                    <td class="text-right">{{ number_format($values['tva'], 2, ',', ' ') }} MAD</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td>TVA ({{ $global_tva_rate ?? '20' }}%)</td>
                                <td class="text-right">{{ number_format($total_tva, 2, ',', ' ') }} MAD</td>
                            </tr>
                        @endif

                        @if(isset($discount_amount) && $discount_amount > 0)
                        <tr style="color: #dc2626;">
                            <td>REMISE COMMERCIALE</td>
                            <td class="text-right">- {{ number_format($discount_amount, 2, ',', ' ') }} MAD</td>
                        </tr>
                        @endif
                        <tr class="grand-total-row">
                            <td>NET À PAYER</td>
                            <td class="text-right">{{ number_format($balance_due ?? $total_ttc, 2, ',', ' ') }} MAD</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-polished">
        <div style="font-weight: 900; font-size: 9px; margin-bottom: 3px; color: #003366;">
            KILATECH SARLAU | CAPITAL SOCIAL DE 100 000 DH | ICE : 003652842000053 | RC : 657473 | IF : 66194148
        </div>
        <div style="margin-bottom: 2px;">
            SIÈGE SOCIAL : 5 RUE ENNOUSSOUR, RDC C/O CASABLANCA, MAROC
        </div>
        <div>
            Fixe : 05 22 72 66 73 | Mobile : +212 7 65 41 97 54 | Email : info@kilatech.ma | Web : www.kilatech.ma
        </div>
        <div class="page-counter"></div>
    </div>

    @endforeach

</body>
</html>
