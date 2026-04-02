<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->date('valid_until')->nullable();
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->string('payment_method')->nullable();
            $table->string('payment_term')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn(['valid_until', 'discount_amount', 'payment_method', 'payment_term']);
        });
    }
};
