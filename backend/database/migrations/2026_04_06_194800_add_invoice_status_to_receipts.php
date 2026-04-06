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
        Schema::table('receipts', function (Blueprint $table) {
            if (!Schema::hasColumn('receipts', 'invoice_status')) {
                $table->string('invoice_status')->default('received')->after('invoice_path');
            }
        });

        // Update existing to 'pending' if no path
        \DB::table('receipts')->whereNull('invoice_path')->update(['invoice_status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('invoice_status');
        });
    }
};
