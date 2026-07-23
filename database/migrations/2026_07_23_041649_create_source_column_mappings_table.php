<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources')->cascadeOnDelete();
            $table->string('target_field');
            $table->string('source_column');
            $table->json('transform')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->userstamps();

            // No DB-level unique(source_id, target_field): MySQL 8 can't cleanly express
            // a partial-unique-excluding-soft-deleted index via Laravel's schema builder,
            // and this table carries softDeletes() like every other admin-managed table.
            // "One active mapping per target field per source" is enforced at the
            // application layer instead (SourceMappingController::update() always
            // upserts via updateOrCreate(['source_id','target_field'], [...])), so
            // duplicates can't be created through the only write path.
            $table->index(['source_id', 'target_field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_column_mappings');
    }
};
