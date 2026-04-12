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
        Schema::table('lots', function (Blueprint $table) {
            // Maya: Drop global unique and make it per-variant 
            // to allow same lot number for different products
            try {
                $table->dropUnique(['lot_number']);
            } catch (\Exception $e) {
                // Fallback for some DB drivers or if index name is different
            }

            $table->date('expiration_date')->nullable()->change();
            $table->decimal('actual_purchase_price', 15, 4)->nullable()->change();
            
            $table->unique(['variant_id', 'lot_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropUnique(['variant_id', 'lot_number']);
            $table->unique('lot_number');
            $table->date('expiration_date')->nullable(false)->change();
            $table->decimal('actual_purchase_price', 15, 4)->nullable(false)->change();
        });
    }
};
