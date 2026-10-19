<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute matching_exports.type : distingue les exports de résultats, de recherche et d'écarts.
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matching_exports', function (Blueprint $table) {
            $table->string('type')->default('matching')->after('filters');
        });
    }

    public function down(): void
    {
        Schema::table('matching_exports', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
