<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute imports.job_dispatched_at : date de mise en file d'attente du traitement.
 * Sert à empêcher qu'un double-clic lance deux fois le même import.
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->timestamp('job_dispatched_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            $table->dropColumn('job_dispatched_at');
        });
    }
};
