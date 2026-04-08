<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Désactiver les contraintes de clés étrangères temporairement
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Vider complètement toutes les tables liées aux achats et réceptions
        DB::table('receipt_lines')->truncate();
        DB::table('receipts')->truncate();
        DB::table('po_lines')->truncate();
        DB::table('purchase_orders')->truncate();

        // Réactiver les contraintes
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // Pas de retour arrière possible
    }
};
