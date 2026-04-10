<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('cin')->nullable()->after('type'); // Pour les particuliers
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->string('source')->default('erp')->after('quote_number'); // 'erp' ou 'web'
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('cin');
        });
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
