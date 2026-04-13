<?php
/**
 * SCRIPT D'URGENCE : Révocation et Régénération SEO
 * A SUPPRIMER IMMÉDIATEMENT APRÈS USAGE
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Bootstrap Laravel pour avoir accès aux modèles
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

header('Content-Type: text/html; charset=utf-8');

echo "<body style='font-family: sans-serif; line-height: 1.6; padding: 40px; color: #333;'>";
echo "<div style='max-width: 600px; margin: 0 auto; border: 1px solid #ddd; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>";

$key = $_GET['key'] ?? '';
if ($key !== 'KILA_SECURE_WIPE_99') {
    http_response_code(403);
    echo "<h2 style='color: #ef4444;'>❌ Accès Interdit</h2>";
    echo "<p>Clé de sécurité invalide.</p>";
    echo "</div></body>";
    exit;
}

echo "<h2 style='color: #2563eb;'>🛠️ Nettoyage Express SEO</h2>";

// 1. Trouver le bot
$user = User::where('email', 'n8n@kilatech.ma')->first();

if (!$user) {
    echo "<p style='color: #ef4444;'>❌ Erreur : Utilisateur n8n_bot non trouvé. Assurez-vous d'avoir déployé les dernières modifications.</p>";
} else {
    // 2. Révoquer TOUS les tokens
    $count = $user->tokens()->delete();
    echo "<p style='color: #059669;'>✅ <b>$count</b> anciens jetons révoqués avec succès.</p>";

    // 3. Nouveau Token
    $token = $user->createToken('n8n-automation-secure', ['seo:manage']);
    
    echo "<div style='background: #f4f4f5; padding: 15px; border-left: 5px solid #2563eb; margin: 20px 0;'>";
    echo "<strong>🚀 VOTRE NOUVEAU TOKEN n8n :</strong><br>";
    echo "<code style='display: block; margin-top: 10px; word-break: break-all; font-size: 1.1em; background: #fff; padding: 10px; border: 1px solid #ccc;'>" . $token->plainTextToken . "</code>";
    echo "</div>";

    echo "<p style='color: #ef4444;'><b>⚠️ ACTION OBLIGATOIRE :</b> Copiez ce token, puis <b>SUPPRIMEZ IMMÉDIATEMENT</b> ce fichier (<code>revoke_seo.php</code>) de votre serveur public_html.</p>";
}

echo "</div></body>";
