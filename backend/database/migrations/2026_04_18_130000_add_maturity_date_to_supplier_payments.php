<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                if (!Schema::hasColumn('supplier_payments', 'maturity_date')) {
                    $table->date('maturity_date')->nullable()->after('payment_date');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                if (Schema::hasColumn('supplier_payments', 'maturity_date')) {
                    $table->dropColumn('maturity_date');
                }
            });
        }
    }
};
