<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('order_lines', 'serial_numbers')) {
                $table->text('serial_numbers')->nullable()->after('lot_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_lines', function (Blueprint $table) {
            $table->dropColumn('serial_numbers');
        });
    }
};
