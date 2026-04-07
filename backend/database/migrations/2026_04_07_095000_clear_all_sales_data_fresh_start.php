<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

        DB::table('invoice_payments')->truncate();
        DB::table('invoices')->truncate();
        DB::table('order_lines')->truncate();
        DB::table('orders')->truncate();
        DB::table('quote_lines')->truncate();
        DB::table('quotes')->truncate();

        // Optional: Reset Auto-increments to 1 for a truly fresh start
        DB::statement('ALTER TABLE quotes AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE orders AUTO_INCREMENT = 1;');
        DB::statement('ALTER TABLE invoices AUTO_INCREMENT = 1;');

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
