<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations. (One-time cleanup for Final Test)
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Clear Sales & Finance
        DB::table('invoice_payments')->delete();
        DB::table('credit_notes')->delete();
        DB::table('invoices')->delete();
        DB::table('order_lines')->delete();
        DB::table('orders')->delete();
        DB::table('quote_lines')->delete();
        DB::table('quotes')->delete();

        // 2. Reset Stock statuses to initial 'disponible' for current test units
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
