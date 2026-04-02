<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('tva_rate', 5, 2)->default(20.00)->after('price_ht');
            $table->decimal('margin_percent', 8, 2)->nullable()->after('tva_rate');
            $table->decimal('public_price', 15, 4)->nullable()->after('margin_percent'); // Prix TTC
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['tva_rate', 'margin_percent', 'public_price']);
        });
    }
};
