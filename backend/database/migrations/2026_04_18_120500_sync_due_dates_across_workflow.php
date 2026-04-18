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
        Schema::table('quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('quotes', 'due_date')) {
                $table->date('due_date')->nullable()->after('valid_until');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'due_date')) {
                $table->date('due_date')->nullable()->after('order_date');
            }
        });
        
        // Populate existing orders from quotes if missing
        DB::statement("
            UPDATE orders o
            JOIN quotes q ON o.quote_id = q.id
            SET o.due_date = COALESCE(q.due_date, q.valid_until, o.order_date)
            WHERE o.due_date IS NULL
        ");
        
        // Populate existing invoices from orders if missing
        DB::statement("
            UPDATE invoices i
            JOIN orders o ON i.order_id = o.id
            SET i.due_date = COALESCE(o.due_date, i.invoice_date)
            WHERE i.due_date IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
