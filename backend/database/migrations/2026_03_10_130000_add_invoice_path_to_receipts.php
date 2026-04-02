<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('receipts', function (Blueprint $row) {
            $row->string('invoice_path')->nullable()->after('notes');
        });
    }

    public function down()
    {
        Schema::table('receipts', function (Blueprint $row) {
            $row->dropColumn('invoice_path');
        });
    }
};
