<?php
// DEBLOQUEUR KILATECH - ACCES DIRECT
header("Access-Control-Allow-Origin: https://kilatech.ma");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

try {
    // 1. Nettoyage Forcé
    Artisan::call('route:clear');
    Artisan::call('cache:clear');
    
    // 2. Test de récupération des articles
    $articles = DB::table('articles')->where('status', 'published')->orderBy('created_at', 'desc')->get();
    
    echo json_encode([
        'status' => 'SUCCESS',
        'message' => 'Serveur Débloqué',
        'count' => count($articles),
        'data' => $articles
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'ERROR',
        'message' => $e->getMessage()
    ]);
}
