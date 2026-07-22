<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
