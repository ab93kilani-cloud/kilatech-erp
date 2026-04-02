<?php
/**
 * Script de secours pour exécuter les migrations Laravel sur Hostinger
 * A placer dans le dossier public_html
 */

// Charger l'autoloader de Composer
require __DIR__.'/../vendor/autoload.php';

// Démarrer Laravel
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo '<html><head><title>Installateur Kilatech</title><style>body{font-family:sans-serif;padding:40px;line-height:1.6;} .success{color:green;font-weight:bold;} .error{color:red;font-weight:bold;}</style></head><body>';
echo '<h1>🚀 Installation de la base de données Kilatech</h1>';
echo '<hr><pre>';

try {
    echo "Démarrage des migrations...\n";
    // migrate:fresh va supprimer les tables existantes et tout recréer proprement
    $status = $kernel->call('migrate:fresh', ['--force' => true]);
    echo "<span class='success'>✅ Migration terminée :</span>\n" . $kernel->output() . "\n";
    
    echo "Installation des données de base (Seeders)...\n";
    $statusSeed = $kernel->call('db:seed', ['--force' => true]);
    echo "<span class='success'>✅ Données de base installées :</span>\n" . $kernel->output() . "\n";

    echo "Nettoyage des caches Laravel...\n";
    $kernel->call('cache:clear');
    $kernel->call('route:clear');
    $kernel->call('config:clear');
    echo "<span class='success'>✅ Caches nettoyés.</span>\n";
    
} catch (Exception $e) {
    echo "<span class='error'>❌ Erreur détectée :</span> " . $e->getMessage() . "\n";
    echo "\nTrace de l'erreur :\n" . $e->getTraceAsString();
}

echo '</pre><hr>';
echo '<h2>✅ Installation terminée !</h2>';
echo '<p>Vous pouvez maintenant :</p>';
echo '<ul>
        <li>Vérifier vos produits sur <a href="/api/products">/api/products</a></li>
        <li><strong>CRITIQUE : Supprimez ce fichier (installer.php)</strong> de votre serveur Hostinger par sécurité.</li>
      </ul>';
echo '</body></html>';
