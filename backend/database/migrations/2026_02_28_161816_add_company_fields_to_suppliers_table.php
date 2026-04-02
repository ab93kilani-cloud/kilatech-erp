<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('ice', 50)->nullable()->after('address');
            $table->string('rc', 50)->nullable()->after('ice');
            $table->string('if', 50)->nullable()->after('rc');
            $table->string('patente', 50)->nullable()->after('if');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['ice', 'rc', 'if', 'patente']);
        });
    }
};
