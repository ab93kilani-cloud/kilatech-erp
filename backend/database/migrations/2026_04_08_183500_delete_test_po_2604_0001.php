<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Supprimer les lignes de réception liées
        DB::table('receipt_lines')->whereIn('receipt_id', function($q) {
            $q->select('id')->from('receipts')->whereIn('purchase_order_id', function($q2) {
                $q2->select('id')->from('purchase_orders')->where('po_number', 'PO-2604-0001');
            });
        })->delete();

        // 2. Supprimer les réceptions liées
        DB::table('receipts')->whereIn('purchase_order_id', function($q) {
            $q->select('id')->from('purchase_orders')->where('po_number', 'PO-2604-0001');
        })->delete();

        // 3. Supprimer les lignes de la commande
        DB::table('po_lines')->whereIn('purchase_order_id', function($q) {
            $q->select('id')->from('purchase_orders')->where('po_number', 'PO-2604-0001');
        })->delete();

        // 4. Supprimer le Bon de commande lui-même
        DB::table('purchase_orders')->where('po_number', 'PO-2604-0001')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas de retour arrière possible pour une suppression manuelle
    }
};
