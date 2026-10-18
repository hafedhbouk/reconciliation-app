<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute aux exceptions le lot (batch_reference) qui les a créées
 * et la case « attendu » (is_expected) pour les non-trouvés normaux.
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exceptions', function (Blueprint $table) {
            $table->string('batch_reference', 36)->nullable()->index();
            $table->boolean('is_expected')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('exceptions', function (Blueprint $table) {
            $table->dropIndex(['batch_reference']);
            $table->dropColumn(['batch_reference', 'is_expected']);
        });
    }
};