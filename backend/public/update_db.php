<?php
// Script de secours direct (sans Laravel)
$host = '127.0.0.1';
$db   = 'kilatech_v3'; // Nom de la base vu dans votre .env
$user = 'root';        // Utilisateur vu dans votre .env
$pass = '';            // Mot de passe vu dans votre .env
$charset = 'utf8mb4';

// Tentative de lecture du .env réel sur le serveur pour les vrais identifiants
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($name, $value) = explode('=', $line, 2);
            $value = trim($value, '"\' ');
            if ($name == 'DB_HOST') $host = $value;
            if ($name == 'DB_DATABASE') $db = $value;
            if ($name == 'DB_USERNAME') $user = $value;
            if ($name == 'DB_PASSWORD') $pass = $value;
        }
    }
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERR_MODE            => PDO::ERR_MODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
     // Ajout des colonnes
     $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS gsc_position DECIMAL(5,2) NULL AFTER seo_score");
     $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS gsc_ctr DECIMAL(5,2) NULL AFTER gsc_position");
     $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS gsc_impressions INT NULL AFTER gsc_ctr");
     
     echo "<h1>✅ SUCCÈS !</h1>";
     echo "<p>Les colonnes Google Search Console ont été ajoutées à la table 'products'.</p>";
     echo "<p><a href='/admin/catalog'>Cliquez ici pour retourner au catalogue</a></p>";

} catch (\PDOException $e) {
     echo "<h1>❌ ERREUR</h1>";
     echo "<p>Impossible de mettre à jour la base : " . $e->getMessage() . "</p>";
}
