<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table matching_rules : les règles de rapprochement entre deux sources.
 * criteria (JSON) contient les paramètres (voir app/Models/MatchingRule.php).
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('source_a_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_b_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('cardinality')->default('1:1');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('criteria')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_rules');
    }
};
