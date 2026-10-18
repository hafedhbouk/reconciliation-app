<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table unmatched_snapshots : cache des écarts entre deux fichiers
 * (un seul cache par couple de fichiers, d'où l'index unique).
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unmatched_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_a_id')->constrained('imports')->cascadeOnDelete();
            $table->foreignId('import_b_id')->constrained('imports')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('result_a')->nullable(); // [{id, reference, amount_millimes, date}, ...]
            $table->json('result_b')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->userstamps();

            $table->unique(['import_a_id', 'import_b_id'], 'unmatched_snapshots_imports_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmatched_snapshots');
    }
};
