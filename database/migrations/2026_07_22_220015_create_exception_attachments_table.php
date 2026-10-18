<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table exception_attachments : pièces jointes des exceptions
 * (le fichier est sur le disque, la table garde son chemin et ses informations).
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exception_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exception_id')->constrained('exceptions')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exception_attachments');
    }
};
