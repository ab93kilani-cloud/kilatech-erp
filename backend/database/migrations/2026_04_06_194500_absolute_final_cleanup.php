<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations. (Absolute Final Cleanup)
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Clear Sales & Finance
        DB::table('invoice_payments')->truncate();
        DB::table('credit_notes')->truncate();
        DB::table('invoices')->truncate();
        DB::table('order_lines')->truncate();
        DB::table('orders')->truncate();
        DB::table('quote_lines')->truncate();
        DB::table('quotes')->truncate();
        DB::table('stock_movements')->where('type', 'out')->delete(); // Uniquement les sorties

        // 2. Reset Stock statuses
        DB::table('stock_units')->update(['status' => 'disponible']);

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse for data cleanup
    }
};
