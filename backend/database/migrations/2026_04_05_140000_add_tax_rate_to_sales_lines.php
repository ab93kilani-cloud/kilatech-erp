<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('quote_lines', 'tax_rate')) {
            Schema::table('quote_lines', function (Blueprint $table) {
                $table->decimal('tax_rate', 5, 2)->default(20.00)->after('quantity');
            });
        }
        
        if (!Schema::hasColumn('order_lines', 'tax_rate')) {
            Schema::table('order_lines', function (Blueprint $table) {
                $table->decimal('tax_rate', 5, 2)->default(20.00)->after('quantity');
            });
        }
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
