<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('purchase_orders', 'po_date')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                // Ajout de la colonne pour gérer la date d'émission manuelle du BC
                $table->date('po_date')->nullable()->after('po_number');
            });
            
            // Initialiser les dates existantes
            \DB::table('purchase_orders')->whereNull('po_date')->update([
                'po_date' => \DB::raw('DATE(created_at)')
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('po_date');
        });
    }
};
