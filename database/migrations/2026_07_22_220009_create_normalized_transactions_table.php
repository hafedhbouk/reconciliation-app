<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crée la table normalized_transactions : la version simplifiée de chaque transaction,
 * utilisée par le rapprochement (une par transaction, d'où unique() sur transaction_id).
 * La colonne normalized_secondary_reference est ajoutée plus tard (migration du 2026_10_16).
 * (Le fonctionnement des migrations est expliqué dans 2026_07_22_220006_create_imports_table.php.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('normalized_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->unique()->constrained('transactions')->cascadeOnDelete();
            $table->string('normalized_reference');
            $table->bigInteger('normalized_amount_millimes');
            $table->date('normalized_date');
            $table->string('dedup_hash')->nullable();
            $table->string('matching_status')->default('unmatched');
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            $table->index('dedup_hash');
            $table->index('normalized_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('normalized_transactions');
    }
};
