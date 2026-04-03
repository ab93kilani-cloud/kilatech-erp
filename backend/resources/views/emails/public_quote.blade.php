<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Votre Devis Kilatech</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; color: #333; }
        .header { background: #003366; color: #fff; padding: 20px; text-align: center; }
        .content { padding: 30px; }
        .footer { font-size: 12px; color: #888; padding: 20px; text-align: center; border-top: 1px solid #eee; }
        .btn { display: inline-block; padding: 12px 24px; background: #003366; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MARC KILATECH</h1>
    </div>
    <div class="content">
        <p>Bonjour,</p>
        <p>Nous avons bien reçu votre demande de devis sur notre site <strong>kilatech.ma</strong>.</p>
        <p>Vous trouverez ci-joint votre devis <strong>{{ $quote->quote_number }}</strong> généré automatiquement.</p>
        <p>Un conseiller commercial prendra contact avec vous prochainement pour discuter de vos besoins.</p>
        
        <p>Merci de votre confiance.</p>
        
        <p>Cordialement,<br>L'équipe KILATECH</p>
    </div>
    <div class="footer">
        SARLAU KILATECH | www.kilatech.ma<br>
        Besoin d'aide ? Contactez-nous au +212 7 65 41 97 54
    </div>
</body>
</html>
