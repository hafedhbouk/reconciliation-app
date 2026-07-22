<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('source_a_id')->constrained('sources')->cascadeOnDelete();
            $table->foreignId('source_b_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->string('cardinality')->default('1:1');
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('criteria')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_rules');
    }
};
