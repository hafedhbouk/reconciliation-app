<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les champs utilisateur manquants pour le profil métier :
     * prénom, nom, matricule et numéro de portable.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('prenom')->nullable()->after('name');
            $table->string('nom')->nullable()->after('prenom');
            $table->string('matricule')->nullable()->unique()->after('nom');
            $table->string('portable')->nullable()->after('matricule');
        });
    }

    /**
     * Supprime les champs ajoutés lors du rollback.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['prenom', 'nom', 'matricule', 'portable']);
        });
    }
};
