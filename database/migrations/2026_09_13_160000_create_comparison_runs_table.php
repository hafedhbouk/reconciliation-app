<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comparison_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_a_id')->constrained('imports');
            $table->foreignId('import_b_id')->constrained('imports');
            $table->string('batch_reference');
            $table->json('criteria');
            $table->json('summary')->nullable();
            $table->json('unmatched_a_ids')->nullable();
            $table->json('unmatched_b_ids')->nullable();
            $table->timestamps();
            $table->unique(['import_a_id', 'import_b_id', 'batch_reference'], 'comparison_run_identity');
        });
        Schema::table('matching_results', function (Blueprint $table) {
            $table->foreignId('comparison_run_id')->nullable()->constrained('comparison_runs');
        });
    }

    public function down(): void
    {
        Schema::table('matching_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comparison_run_id');
        });
        Schema::dropIfExists('comparison_runs');
    }
};
