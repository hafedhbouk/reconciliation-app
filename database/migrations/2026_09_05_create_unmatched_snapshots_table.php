<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unmatched_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_a_id')->constrained('imports')->cascadeOnDelete();
            $table->foreignId('import_b_id')->constrained('imports')->cascadeOnDelete();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->json('result_a')->nullable(); // [{id, reference, amount_millimes, date}, ...]
            $table->json('result_b')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->userstamps();

            $table->unique(['import_a_id', 'import_b_id'], 'unmatched_snapshots_imports_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unmatched_snapshots');
    }
};
