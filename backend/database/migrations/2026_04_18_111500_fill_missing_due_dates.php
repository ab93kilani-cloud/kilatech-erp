<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pour les factures (Ventes)
        // On initialise la date d'échéance à la date de facture SI elle est absente.
        // On prend invoice_date en priorité, sinon created_at.
        DB::table('invoices')
            ->whereNull('due_date')
            ->update([
                'due_date' => DB::raw("COALESCE(invoice_date, created_at)")
            ]);

        // 2. Pour les Bons de Commande (Achats)
        // On initialise la date d'échéance à la date du PO ou livraison attendue.
        DB::table('purchase_orders')
            ->whereNull('due_date')
            ->update([
                'due_date' => DB::raw("COALESCE(expected_delivery_date, po_date, created_at)")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // On ne revient pas en arrière pour les données migrées par défaut
    }
};
