<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    Schema::table('categories', function (Blueprint $table) {
        if (!Schema::hasColumn('categories', 'description')) {
            $table->longText('description')->nullable()->after('slug');
        }
        if (!Schema::hasColumn('categories', 'seo_title')) {
            $table->string('seo_title')->nullable()->after('description');
        }
        if (!Schema::hasColumn('categories', 'seo_description')) {
            $table->text('seo_description')->nullable()->after('seo_title');
        }
    });
    echo "Columns added successfully to categories table.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
