<?php
/**
 * ============================================================
 * KILATECH — Script d'installation & configuration serveur
 * ============================================================
 * INSTRUCTIONS :
 *  1. Uploadez ce fichier dans /public_html/api/public/
 *  2. Accédez à https://app.kilatech.ma/install.php
 *  3. Suivez les étapes dans l'ordre
 *  4. SUPPRIMEZ ce fichier après installation !
 * ============================================================
 */

// Clé de sécurité — changez-la avant d'uploader !
define('INSTALL_KEY', 'kilatech-install-2026');

$key = $_GET['key'] ?? '';
$step = $_GET['step'] ?? 'menu';

if ($key !== INSTALL_KEY) {
    die('<h1 style="color:red;font-family:sans-serif;">⛔ Accès refusé. Ajoutez ?key=kilatech-install-2026 à l\'URL.</h1>');
}

// Styles
echo '<!DOCTYPE html><html lang="fr"><head>
<meta charset="UTF-8">
<title>Kilatech — Installation</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
  h1 { color: #1a56db; } h2 { color: #333; border-bottom: 2px solid #1a56db; padding-bottom: 8px; }
  .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
  .btn { display: inline-block; padding: 10px 24px; background: #1a56db; color: white; text-decoration: none; border-radius: 6px; margin: 8px 4px; font-size: 15px; }
  .btn-red { background: #dc2626; }
  .btn-green { background: #16a34a; }
  .btn-orange { background: #d97706; }
  pre { background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; }
  .ok { color: #16a34a; font-weight: bold; }
  .err { color: #dc2626; font-weight: bold; }
  .warn { color: #d97706; font-weight: bold; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 8px 12px; border: 1px solid #ddd; text-align: left; }
  th { background: #f0f4ff; }
</style>
</head><body>';

echo '<h1>🚀 Kilatech — Panneau d\'Installation</h1>';
echo '<div class="card"><b>⚠️ IMPORTANT :</b> Supprimez ce fichier immédiatement après l\'installation terminée !</div>';

$base_key = "?key=" . INSTALL_KEY;

// ──────────────────────────────────────────────────────────────
// MENU PRINCIPAL
// ──────────────────────────────────────────────────────────────
if ($step === 'menu') {
    echo '<div class="card">
    <h2>📋 Étapes d\'installation</h2>
    <table>
    <tr><th>Étape</th><th>Description</th><th>Action</th></tr>
    <tr><td>1</td><td>🔍 Vérification de l\'environnement PHP</td><td><a class="btn" href="'.$base_key.'&step=check_php">Vérifier</a></td></tr>
    <tr><td>2</td><td>📝 Créer le fichier .env</td><td><a class="btn btn-orange" href="'.$base_key.'&step=create_env">Configurer</a></td></tr>
    <tr><td>3</td><td>🗄️ Créer les tables de la base de données</td><td><a class="btn btn-orange" href="'.$base_key.'&step=migrate">Migrer DB</a></td></tr>
    <tr><td>4</td><td>🔗 Créer le lien storage (images)</td><td><a class="btn btn-orange" href="'.$base_key.'&step=storage_link">Storage Link</a></td></tr>
    <tr><td>5</td><td>👤 Créer le compte administrateur</td><td><a class="btn btn-green" href="'.$base_key.'&step=create_admin">Créer Admin</a></td></tr>
    <tr><td>6</td><td>🧹 Vider les caches Laravel</td><td><a class="btn" href="'.$base_key.'&step=clear_cache">Vider Cache</a></td></tr>
    <tr><td>⚠️</td><td>🗑️ Supprimer ce script (OBLIGATOIRE)</td><td><a class="btn btn-red" href="'.$base_key.'&step=self_delete" onclick="return confirm(\'Supprimer ce fichier ?\')">Supprimer</a></td></tr>
    </table>
    </div>';
}

// ──────────────────────────────────────────────────────────────
// ÉTAPE 1 : Vérification PHP
// ──────────────────────────────────────────────────────────────
elseif ($step === 'check_php') {
    echo '<div class="card"><h2>🔍 Vérification de l\'environnement</h2><table>';
    
    $checks = [
        ['PHP Version (8.1+)',   version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
        ['Extension PDO',        extension_loaded('pdo'), ''],
        ['Extension PDO MySQL',  extension_loaded('pdo_mysql'), ''],
        ['Extension mbstring',   extension_loaded('mbstring'), ''],
        ['Extension openssl',    extension_loaded('openssl'), ''],
        ['Extension tokenizer',  extension_loaded('tokenizer'), ''],
        ['Extension xml',        extension_loaded('xml'), ''],
        ['Extension ctype',      extension_loaded('ctype'), ''],
        ['Extension fileinfo',   extension_loaded('fileinfo'), ''],
        ['Extension json',       extension_loaded('json'), ''],
        ['Extension gd',         extension_loaded('gd'), '(pour images)'],
        ['storage/ accessible',  is_writable(__DIR__ . '/../storage'), ''],
        ['bootstrap/cache/ accessible', is_writable(__DIR__ . '/../bootstrap/cache'), ''],
    ];
    
    echo '<tr><th>Vérification</th><th>Statut</th><th>Info</th></tr>';
    foreach ($checks as $c) {
        $status = $c[1] ? '<span class="ok">✅ OK</span>' : '<span class="err">❌ ERREUR</span>';
        echo "<tr><td>{$c[0]}</td><td>$status</td><td>{$c[2]}</td></tr>";
    }
    echo '</table>';
    echo '<br><a class="btn" href="'.$base_key.'&step=menu">← Retour au menu</a>';
    echo '</div>';
}

// ──────────────────────────────────────────────────────────────
// ÉTAPE 2 : Créer/Afficher le .env
// ──────────────────────────────────────────────────────────────
elseif ($step === 'create_env') {
    echo '<div class="card"><h2>📝 Configuration du fichier .env</h2>';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $db_name = trim($_POST['db_name'] ?? '');
        $db_user = trim($_POST['db_user'] ?? '');
        $db_pass = trim($_POST['db_pass'] ?? '');
        $app_key = 'base64:P1LT/a2u5Gy5C3kwA3SD/+ctacC5F3SSEcvgUJ/ya3k=';
        
        $env_content = "APP_NAME=Kilatech
APP_ENV=production
APP_KEY={$app_key}
APP_DEBUG=false
APP_URL=https://app.kilatech.ma

LOG_CHANNEL=stack
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE={$db_name}
DB_USERNAME={$db_user}
DB_PASSWORD={$db_pass}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=contact@kilatech.ma
MAIL_PASSWORD=" . trim($_POST['mail_pass'] ?? '') . "
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=\"contact@kilatech.ma\"
MAIL_FROM_NAME=\"Kilatech\"

SANCTUM_STATEFUL_DOMAINS=kilatech.ma,app.kilatech.ma
SESSION_DOMAIN=.kilatech.ma
";
        
        $env_path = __DIR__ . '/../.env';
        if (file_put_contents($env_path, $env_content)) {
            echo '<p class="ok">✅ Fichier .env créé avec succès !</p>';
        } else {
            echo '<p class="err">❌ Impossible de créer le .env. Vérifiez les permissions du dossier /api/</p>';
        }
    }
    
    echo '<form method="POST">
    <table>
    <tr><td><b>Nom de la base de données</b></td><td><input name="db_name" style="width:300px;padding:6px;" placeholder="ex: u123456789_kilatech" required></td></tr>
    <tr><td><b>Utilisateur MySQL</b></td><td><input name="db_user" style="width:300px;padding:6px;" placeholder="ex: u123456789_user" required></td></tr>
    <tr><td><b>Mot de passe MySQL</b></td><td><input type="password" name="db_pass" style="width:300px;padding:6px;" required></td></tr>
    <tr><td><b>Mot de passe email contact@kilatech.ma</b></td><td><input type="password" name="mail_pass" style="width:300px;padding:6px;"></td></tr>
    </table>
    <br><button type="submit" class="btn btn-green">💾 Créer le .env</button>
    </form>';
    echo '<br><a class="btn" href="'.$base_key.'&step=menu">← Retour au menu</a>';
    echo '</div>';
}

// ──────────────────────────────────────────────────────────────
// ÉTAPE 3 : Migrations
// ──────────────────────────────────────────────────────────────
elseif ($step === 'migrate') {
    echo '<div class="card"><h2>🗄️ Migration de la base de données</h2>';
    
    // Bootstrap Laravel
    $app_path = __DIR__ . '/..';
    
    ob_start();
    try {
        require $app_path . '/vendor/autoload.php';
        $app = require_once $app_path . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();
        
        echo '<p class="ok">✅ Migrations exécutées !</p>';
        echo '<pre>' . htmlspecialchars($output) . '</pre>';
    } catch (\Exception $e) {
        echo '<p class="err">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
    ob_end_flush();
    
    echo '<br><a class="btn" href="'.$base_key.'&step=menu">← Retour au menu</a>';
    echo '</div>';
}

// ──────────────────────────────────────────────────────────────
// ÉTAPE 4 : Storage Link
// ──────────────────────────────────────────────────────────────
elseif ($step === 'storage_link') {
    echo '<div class="card"><h2>🔗 Lien Storage</h2>';
    
    $public_path = __DIR__;
    $storage_path = __DIR__ . '/../storage/app/public';
    $link_path = __DIR__ . '/storage';
    
    if (is_link($link_path)) {
        echo '<p class="warn">⚠️ Le lien storage existe déjà.</p>';
    } else {
        if (symlink($storage_path, $link_path)) {
            echo '<p class="ok">✅ Lien storage créé avec succès !</p>';
        } else {
            // Fallback : créer un .htaccess de redirection
            echo '<p class="warn">⚠️ symlink() non disponible. Utilisation du fallback .htaccess...</p>';
            @mkdir($link_path, 0755, true);
            $htaccess = "Options -Indexes\nRewriteEngine On\nRewriteRule ^(.*)$ ../storage/app/public/\$1 [L]";
            if (file_put_contents($link_path . '/.htaccess', $htaccess)) {
                echo '<p class="ok">✅ Fallback .htaccess créé dans /public/storage/</p>';
            } else {
                echo '<p class="err">❌ Impossible de créer le lien storage.</p>';
            }
        }
    }
    
    // Permissions
    chmod($storage_path, 0775);
    echo '<p>📁 Chemin storage : <code>' . $storage_path . '</code></p>';
    echo '<br><a class="btn" href="'.$base_key.'&step=menu">← Retour au menu</a>';
    echo '</div>';
}

// ──────────────────────────────────────────────────────────────
// ÉTAPE 5 : Créer Admin
// ──────────────────────────────────────────────────────────────
elseif ($step === 'create_admin') {
    echo '<div class="card"><h2>👤 Créer le compte Administrateur</h2>';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            require __DIR__ . '/../vendor/autoload.php';
            $app = require_once __DIR__ . '/../bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            $kernel->bootstrap();
            
            $name  = trim($_POST['name'] ?? 'Admin');
            $email = trim($_POST['email'] ?? '');
            $pass  = trim($_POST['password'] ?? '');
            
            // Chercher le rôle Admin
            $role = \App\Models\Role::where('name', 'Admin')->first();
            
            $user = \App\Models\User::create([
                'name'      => $name,
                'email'     => $email,
                'password'  => \Illuminate\Support\Facades\Hash::make($pass),
                'role_id'   => $role ? $role->id : 1,
            ]);
            
            echo '<p class="ok">✅ Administrateur créé : ' . htmlspecialchars($email) . '</p>';
        } catch (\Exception $e) {
            echo '<p class="err">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
    
    echo '<form method="POST">
    <table>
    <tr><td><b>Nom</b></td><td><input name="name" value="Administrateur" style="width:280px;padding:6px;"></td></tr>
    <tr><td><b>Email</b></td><td><input name="email" type="email" style="width:280px;padding:6px;" placeholder="admin@kilatech.ma" required></td></tr>
    <tr><td><b>Mot de passe</b></td><td><input name="password" type="password" style="width:280px;padding:6px;" required></td></tr>
    </table>
    <br><button type="submit" class="btn btn-green">✅ Créer l\'administrateur</button>
    </form>';
    echo '<br><a class="btn" href="'.$base_key.'&step=menu">← Retour au menu</a>';
    echo '</div>';
}

// ──────────────────────────────────────────────────────────────
// ÉTAPE 6 : Vider les caches
// ──────────────────────────────────────────────────────────────
elseif ($step === 'clear_cache') {
    echo '<div class="card"><h2>🧹 Vider les caches Laravel</h2>';
    
    try {
        require __DIR__ . '/../vendor/autoload.php';
        $app = require_once __DIR__ . '/../bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        
        Artisan::call('config:clear');
        echo '<p class="ok">✅ config:clear — OK</p>';
        Artisan::call('cache:clear');
        echo '<p class="ok">✅ cache:clear — OK</p>';
        Artisan::call('view:clear');
        echo '<p class="ok">✅ view:clear — OK</p>';
        Artisan::call('route:clear');
        echo '<p class="ok">✅ route:clear — OK</p>';
        Artisan::call('config:cache');
        echo '<p class="ok">✅ config:cache — OK</p>';
        
    } catch (\Exception $e) {
        echo '<p class="err">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    
    echo '<br><a class="btn" href="'.$base_key.'&step=menu">← Retour au menu</a>';
    echo '</div>';
}

// ──────────────────────────────────────────────────────────────
// AUTO-SUPPRESSION
// ──────────────────────────────────────────────────────────────
elseif ($step === 'self_delete') {
    if (unlink(__FILE__)) {
        echo '<div class="card"><p class="ok">✅ Script supprimé avec succès. Votre installation est sécurisée.</p></div>';
    } else {
        echo '<div class="card"><p class="err">❌ Impossible de supprimer ce fichier automatiquement. Supprimez-le manuellement via le File Manager Hostinger.</p></div>';
    }
}

echo '</body></html>';
