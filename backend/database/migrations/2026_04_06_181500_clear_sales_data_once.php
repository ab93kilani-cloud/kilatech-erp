<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Clear Invoices and Payments
        DB::table('invoice_payments')->delete();
        DB::table('invoice_items')->delete();
        DB::table('invoices')->delete();

        // 2. Clear Orders (BL)
        DB::table('order_lines')->delete();
        DB::table('orders')->delete();

        // 3. Clear Quotes
        DB::table('quote_lines')->delete();
        DB::table('quotes')->delete();
    }

    public function down(): void
    {
        // No action needed for down
    }
};
