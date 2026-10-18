<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table currencies : devises, avec leur nombre de décimales
 * (3 pour le dinar tunisien, d'où les montants en millimes).
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('iso_code', 3)->unique();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
