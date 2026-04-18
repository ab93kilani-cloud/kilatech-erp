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
        // On essaye d'abord de récupérer 'valid_until' du devis lié via la commande.
        // Sinon on retombe sur invoice_date ou created_at.
        DB::statement("
            UPDATE invoices i
            JOIN orders o ON i.order_id = o.id
            JOIN quotes q ON o.quote_id = q.id
            SET i.due_date = COALESCE(q.valid_until, i.invoice_date, i.created_at)
            WHERE i.due_date IS NULL
        ");

        // Pour les factures sans devis lié (cas rares ou passage direct)
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
