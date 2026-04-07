<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->enum('type', ['purchase_invoice', 'rent_receipt', 'salary_cnss']);
            $table->integer('month')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manager_documents');
    }
};
