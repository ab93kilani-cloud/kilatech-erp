<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations. (Absolute Inventory Wipe for Test)
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Wipe all inventory
        DB::table('stock_units')->truncate();
        DB::table('stock_movements')->truncate();
        
        // 2. Also ensure receipts and POs are still clean for this final cycle
        DB::table('receipt_lines')->truncate();
        DB::table('receipts')->truncate();
        DB::table('po_lines')->truncate();
        DB::table('purchase_orders')->truncate();

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
