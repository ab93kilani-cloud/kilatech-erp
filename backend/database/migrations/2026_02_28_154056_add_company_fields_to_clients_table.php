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
        Schema::table('clients', function (Blueprint $table) {
            $table->enum('type', ['company', 'individual'])->default('company')->after('id');
            $table->string('company_name')->nullable()->change();
            $table->string('ice')->nullable()->after('address');
            $table->string('rc')->nullable()->after('ice');
            $table->string('if')->nullable()->after('rc');
            $table->string('patente')->nullable()->after('if');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['type', 'ice', 'rc', 'if', 'patente']);
            $table->string('company_name')->nullable(false)->change();
        });
    }
};
