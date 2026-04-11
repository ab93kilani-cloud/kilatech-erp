<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $blueprint) {
            $blueprint->string('unit_name')->nullable()->default('unité')->after('public_price');
            $blueprint->string('packaging_unit')->nullable()->default('pièce')->after('unit_name');
            $blueprint->integer('sales_multiplier')->default(1)->after('packaging_unit');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['unit_name', 'packaging_unit', 'sales_multiplier']);
        });
    }
};
