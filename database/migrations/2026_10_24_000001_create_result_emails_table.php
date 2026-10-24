<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envoi des résultats de rapprochement par e-mail (Phase 4).
 *
 * Migration uniquement additive : une colonne facultative sur operations et
 * une nouvelle table. Aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ce qu'il faut pour retrouver les résultats d'un rapprochement terminé
        // (lot, fichiers comparés, règle) : sert à préparer l'e-mail et sa pièce jointe.
        Schema::table('operations', function (Blueprint $table) {
            $table->json('context')->nullable()->after('result_url');
        });

        // Un envoi = un destinataire d'un rapprochement : sert de trace et empêche
        // d'envoyer deux fois les mêmes résultats à la même personne.
        Schema::create('result_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained('operations')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');                       // adresse au moment de la demande
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('with_attachment')->default(false);
            // waiting (rapprochement pas encore terminé), queued, sending, sent, failed, cancelled
            $table->string('status', 16)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('attachment_name')->nullable(); // fichier réellement joint
            $table->string('note')->nullable();            // ex. pièce jointe trop volumineuse
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['operation_id', 'email']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_emails');
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
