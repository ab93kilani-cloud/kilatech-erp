<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // --- Contenu SEO ---
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->longText('seo_content')->nullable();
            $table->string('seo_h1')->nullable();
            
            // --- Métadonnées de Contrôle ---
            $table->string('seo_hash', 64)->nullable(); // SHA256 length is 64
            $table->enum('seo_status', ['pending', 'optimized', 'review', 'manual'])->default('pending');
            $table->enum('seo_priority', ['high', 'medium', 'low'])->default('medium');
            
            // --- Scoring & Metrics ---
            $table->integer('seo_score')->default(0); // Score interne (0-100)
            $table->json('seo_audit')->nullable();    // Détails du calcul (nb_mots, villes, hn)
            
            // --- Futur : Google Search Console ---
            $table->float('gsc_position')->nullable(); 
            $table->decimal('gsc_ctr', 5, 2)->nullable(); // CTR expressed in percentage (ex: 12.50%)
            $table->integer('gsc_impressions')->nullable();
            
            $table->timestamp('last_seo_update')->nullable();

            // --- Indexes ---
            $table->index('seo_hash'); 
            $table->index('seo_status');
            $table->index('seo_score');
            $table->index('seo_priority');
        });

        // Table d'historique pour le versioning
        Schema::create('seo_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->longText('seo_content')->nullable();
            $table->string('seo_h1')->nullable();
            $table->string('seo_hash', 64)->nullable();
            $table->integer('seo_score')->nullable();
            $table->json('seo_audit')->nullable();
            $table->timestamps(); 
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['seo_hash']);
            $table->dropIndex(['seo_status']);
            $table->dropIndex(['seo_score']);
            $table->dropIndex(['seo_priority']);
            $table->dropColumn([
                'seo_title', 'seo_description', 'seo_content', 'seo_h1',
                'seo_hash', 'seo_status', 'seo_priority', 'seo_score', 
                'seo_audit', 'gsc_position', 'gsc_ctr', 'gsc_impressions', 'last_seo_update'
            ]);
        });
        Schema::dropIfExists('seo_versions');
    }
};
