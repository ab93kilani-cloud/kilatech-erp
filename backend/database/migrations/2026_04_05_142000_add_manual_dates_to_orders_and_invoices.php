<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'order_date')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->date('order_date')->nullable()->after('order_number');
            });
        }
        
        if (!Schema::hasColumn('invoices', 'invoice_date')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->date('invoice_date')->nullable()->after('invoice_number');
            });
        }

        // Initialize existing records
        \DB::table('orders')->whereNull('order_date')->update([
            'order_date' => \DB::raw('DATE(created_at)')
        ]);
        
        \DB::table('invoices')->whereNull('invoice_date')->update([
            'invoice_date' => \DB::raw('DATE(created_at)')
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_date');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_date');
        });
    }
};
