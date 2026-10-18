<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table matching_details : relie un résultat à chacune de ses transactions,
 * avec le côté ('a' ou 'b'). Une transaction n'apparaît qu'une fois par résultat (index unique).
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matching_result_id')->constrained('matching_results')->cascadeOnDelete();
            $table->foreignId('normalized_transaction_id')->constrained('normalized_transactions')->cascadeOnDelete();
            $table->string('side')->nullable();
            $table->timestamps();

            $table->unique(['matching_result_id', 'normalized_transaction_id'], 'matching_details_result_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_details');
    }
};
