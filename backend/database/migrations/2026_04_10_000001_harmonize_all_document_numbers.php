<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Quote;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\CreditNote;
use App\Models\Receipt;

return new class extends Migration
{
    public function up()
    {
        // 1. HARMONISATION DEVIS (DEV-)
        foreach (Quote::withTrashed()->orderBy('id')->get() as $i => $q) {
            $num = 'DEV-2026-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $q->update(['quote_number' => $num]);
        }

        // 2. HARMONISATION BL (BL-)
        foreach (Order::withTrashed()->orderBy('id')->get() as $i => $o) {
            $num = 'BL-2026-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $o->update(['order_number' => $num]);
        }

        // 3. HARMONISATION FACTURES (FA-)
        foreach (Invoice::withTrashed()->orderBy('id')->get() as $i => $inv) {
            $num = 'FA-2026-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $inv->update(['invoice_number' => $num]);
        }

        // 4. HARMONISATION AVOIRS (AV-)
        foreach (CreditNote::withTrashed()->orderBy('id')->get() as $i => $cn) {
            $num = 'AV-2026-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $cn->update(['credit_note_number' => $num]);
        }

        // 5. HARMONISATION RECEPTIONS (REC-)
        foreach (Receipt::withTrashed()->orderBy('id')->get() as $i => $r) {
            $num = 'REC-2026-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            $r->update(['receipt_number' => $num]);
        }
    }

    public function down() {}
};
