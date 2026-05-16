<?php
require __DIR__ . '/api/vendor/autoload.php';
$app = require_once __DIR__ . '/api/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    if (!Schema::hasColumn('products', 'gsc_position')) {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('gsc_position', 5, 2)->nullable()->after('seo_score');
            $table->decimal('gsc_ctr', 5, 2)->nullable()->after('gsc_position');
            $table->integer('gsc_impressions')->nullable()->after('gsc_ctr');
        });
        echo "✅ Base de données mise à jour avec succès ! Les colonnes GSC ont été ajoutées.";
    } else {
        echo "ℹ️ La base de données était déjà à jour.";
    }
} catch (\Exception $e) {
    echo "❌ Erreur lors de la mise à jour : " . $e->getMessage();
}
