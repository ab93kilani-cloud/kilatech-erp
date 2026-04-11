<?php
/**
 * Nuclear Database Fix for Hostinger.
 * Manually adds missing columns to bypass migration logic issues.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

echo "=== FIXING DATABASE COLUMNS MAYA ===\n\n";

try {
    if (!Schema::hasColumn('quotes', 'source')) {
        Schema::table('quotes', function (Blueprint $table) {
            $table->string('source')->default('erp')->after('quote_number');
        });
        echo "✅ Column 'source' added to 'quotes'\n";
    } else {
        echo "ℹ️ Column 'source' already exists in 'quotes'\n";
    }

    Schema::table('clients', function (Blueprint $table) {
        if (!Schema::hasColumn('clients', 'source')) {
            $table->string('source')->default('erp')->after('id');
            echo "✅ Column 'source' added to 'clients'\n";
        } else {
            echo "ℹ️ Column 'source' already exists in 'clients'\n";
        }
        
        if (!Schema::hasColumn('clients', 'cin')) {
            $table->string('cin')->nullable()->after('id');
            echo "✅ Column 'cin' added to 'clients'\n";
        } else {
            echo "ℹ️ Column 'cin' already exists in 'clients'\n";
        }
    });

    if (!Schema::hasTable('contact_messages')) {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('status')->default('unread'); // unread, read, replied
            $table->timestamps();
        });
        echo "✅ Table 'contact_messages' created\n";
    } else {
        echo "ℹ️ Table 'contact_messages' already exists\n";
    }

    echo "\n\nFIX COMPLETED! ✅ Maya.";
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage();
}
