<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('quote_lines', 'client_product_ref')) {
                $table->string('client_product_ref')->nullable()->after('variant_id');
            }
        });

        Schema::table('order_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('order_lines', 'client_product_ref')) {
                $table->string('client_product_ref')->nullable()->after('variant_id');
            }
            if (!Schema::hasColumn('order_lines', 'lot_number')) {
                $table->string('lot_number')->nullable()->after('client_product_ref');
            }
            if (!Schema::hasColumn('order_lines', 'expiry_date')) {
                $table->date('expiry_date')->nullable()->after('lot_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropColumn('client_product_ref');
        });
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn(['client_product_ref', 'lot_number', 'expiry_date']);
        });
    }
};
