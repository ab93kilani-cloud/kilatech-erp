<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\PurchaseOrder;

return new class extends Migration
{
    public function up()
    {
        // Renommer tous les PO existants dans l'ordre de leur création
        $pos = PurchaseOrder::withTrashed()->orderBy('id')->get();
        foreach ($pos as $index => $po) {
            $newNumber = 'PO-2026-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
            $po->update(['po_number' => $newNumber]);
        }
    }

    public function down()
    {
        // Pas de retour en arrière automatique nécessaire pour cette modification cosmétique
    }
};
