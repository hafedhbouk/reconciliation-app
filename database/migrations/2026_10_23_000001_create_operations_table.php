<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table operations : le suivi en temps réel des traitements longs
 * (import, rapprochement, comparaison de fichiers, exports).
 *
 * Une ligne = une opération lancée par un utilisateur. Les jobs y écrivent
 * leur avancement réel (voir App\Services\OperationTracker), et l'écran
 * « Mes opérations » la relit régulièrement. Migration additive : aucune
 * table existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();               // identifiant public (URL, JavaScript)
            $table->string('type', 32);                   // import, matching, comparison, export, search_export
            $table->string('label');                      // ex. « Import BNA-07-2026.xlsx »
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');            // objet suivi : import, export, comparaison…
            $table->string('status', 16)->default('pending'); // pending, running, completed, failed
            $table->string('stage')->nullable();          // étape actuelle, ex. « Écriture du fichier »
            $table->boolean('indeterminate')->default(false); // étape dont l'avancement n'est pas mesurable
            $table->unsignedBigInteger('processed')->default(0);
            $table->unsignedBigInteger('total')->nullable(); // null = total inconnu (pas de pourcentage)
            $table->unsignedBigInteger('succeeded')->nullable();
            $table->unsignedBigInteger('failed')->nullable();
            $table->text('error_message')->nullable();
            $table->json('summary')->nullable();          // résultats chiffrés à la fin
            $table->string('result_url')->nullable();     // page des résultats
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable(); // dernier signe de vie du job
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};
