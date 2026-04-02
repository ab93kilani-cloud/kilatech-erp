<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #003366;
            color: #ffffff;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-top: none;
            border-radius: 0 0 5px 5px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #718096;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #003366;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Votre Devis Kilatech</h1>
    </div>
    <div class="content">
        <p>Bonjour {{ $quote->client->contact_name }},</p>
        <p>Nous vous remercions de votre intérêt pour les solutions <strong>Kilatech</strong>.</p>
        <p>Veuillez trouver en pièce jointe votre devis n° <strong>{{ $quote->quote_number }}</strong> généré suite à votre demande sur notre boutique en ligne.</p>
        <p>Notre équipe commerciale vous contactera prochainement pour discuter de vos besoins spécifiques et vous accompagner dans votre projet.</p>
        <p>Cordialement,</p>
        <p>L'équipe KILATECH</p>
    </div>
    <div class="footer">
        <p>Kilatech SARLAU - 5 RUE ENNOUSSOUR, RDC C/O CASABLANCA</p>
        <p>Tél : 0522726673 | www.kilatech.ma</p>
    </div>
</body>
</html>
