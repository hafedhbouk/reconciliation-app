<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matching_rule_id')->nullable()->constrained('matching_rules')->nullOnDelete();
            $table->string('batch_reference')->nullable();
            $table->string('status')->default('matched');
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->foreignId('matched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('matched_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_results');
    }
};
