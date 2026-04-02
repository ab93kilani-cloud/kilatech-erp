<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants');
            $table->foreignId('supplier_id')->constrained('suppliers');
            $table->string('supplier_ref')->nullable();
            $table->decimal('purchase_price', 15, 4);
            $table->integer('lead_time_days')->nullable();
            $table->date('price_validity_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
    }
};
