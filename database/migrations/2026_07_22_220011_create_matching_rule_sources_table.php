<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_rule_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matching_rule_id')->constrained('matching_rules')->cascadeOnDelete();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->timestamps();

            $table->unique(['matching_rule_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_rule_sources');
    }
};
