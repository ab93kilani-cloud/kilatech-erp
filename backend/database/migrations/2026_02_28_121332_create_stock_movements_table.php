<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('stock_units');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->enum('type', ['in', 'out', 'status_change']);
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('reference_doc_type')->nullable();
            $table->unsignedBigInteger('reference_doc_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
