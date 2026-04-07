<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Disable foreign key checks to allow clearing tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // SALES
        DB::table('invoice_payments')->truncate();
        DB::table('invoices')->truncate();
        DB::table('credit_notes')->truncate();
        DB::table('order_lines')->truncate();
        DB::table('orders')->truncate();
        DB::table('quote_lines')->truncate();
        DB::table('quotes')->truncate();

        // STOCK & PURCHASES
        DB::table('stock_movements')->truncate();
        DB::table('stock_units')->truncate();
        DB::table('lots')->truncate();
        DB::table('receipt_lines')->truncate();
        DB::table('receipts')->truncate();
        DB::table('po_lines')->truncate();
        DB::table('purchase_orders')->truncate();

        // RESET IDS (Auto-increments)
        DB::statement('ALTER TABLE quotes AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE orders AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE invoices AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE credit_notes AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE purchase_orders AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE receipts AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE lots AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE stock_units AUTO_INCREMENT = 1;');

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse for data wipe
    }
};
