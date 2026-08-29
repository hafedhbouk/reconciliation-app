<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table de suivi des exports asynchrones de résultats de rapprochement.
     *
     * Chaque ligne représente une demande d'export (CSV/XLSX/PDF) générée
     * en arrière-plan par GenerateMatchingExportJob. Le fichier final est
     * stocké sur le disque local et un lien de téléchargement est envoyé
     * par notification à l'utilisateur déclencheur.
     */
    public function up(): void
    {
        Schema::create('matching_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('format')->default('csv'); // csv | xlsx | pdf
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->string('file_path')->nullable();
            $table->string('download_token')->nullable()->unique();
            $table->text('filters')->nullable(); // JSON: rule, batch, date range, status
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            $table->index(['user_id', 'status']);
            $table->index('download_token');
        });
    }

    /**
     * Supprime la table d'exports asynchrones.
     */
    public function down(): void
    {
        Schema::dropIfExists('matching_exports');
    }
};
