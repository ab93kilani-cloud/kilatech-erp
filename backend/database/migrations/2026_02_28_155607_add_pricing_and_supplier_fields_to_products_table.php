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
        Schema::table('products', function (Blueprint $table) {
            $table->string('reference')->unique()->nullable()->after('id');
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete()->after('category');
            $table->decimal('cost_price', 15, 4)->default(0)->after('description'); // Prix d'achat HT
            $table->decimal('price_ht', 15, 4)->default(0)->after('cost_price');    // Prix de vente HT
            $table->decimal('tva_rate', 5, 2)->default(20.00)->after('price_ht');   // Taux de TVA %
            $table->decimal('margin_percent', 8, 2)->default(0)->after('public_price'); // Marge %
            // renaming public_price to price_ttc virtually handled or we keep public_price as TTC
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn(['reference', 'supplier_id', 'cost_price', 'price_ht', 'tva_rate', 'margin_percent']);
        });
    }
};
