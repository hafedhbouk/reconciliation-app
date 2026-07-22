<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('normalized_transaction_id')->nullable()->constrained('normalized_transactions')->nullOnDelete();
            $table->foreignId('matching_result_id')->nullable()->constrained('matching_results')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_comment')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exceptions');
    }
};
