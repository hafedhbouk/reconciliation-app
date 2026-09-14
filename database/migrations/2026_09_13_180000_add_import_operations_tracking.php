<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('import_rows')->select('import_id', 'row_number')->groupBy('import_id', 'row_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Des numéros de lignes sont déjà dupliqués dans un import. Les vérifier avant cette migration.');
        }
        Schema::table('import_rows', fn (Blueprint $table) => $table->unique(['import_id', 'row_number'], 'import_row_identity'));
        Schema::table('imports', function (Blueprint $table) {
            $table->json('mapping_snapshot')->nullable();
            $table->string('mapping_hash', 64)->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->string('processing_file_hash', 64)->nullable();
        });
        Schema::table('unmatched_snapshots', function (Blueprint $table) {
            $table->boolean('rows_persisted')->default(false);
            $table->json('file_totals')->nullable();
        });
        Schema::table('comparison_runs', function (Blueprint $table) {
            $table->json('file_totals')->nullable();
            $table->timestamp('invalidated_at')->nullable();
        });
        Schema::create('unmatched_snapshot_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('unmatched_snapshots')->cascadeOnDelete();
            $table->string('side', 1);
            $table->unsignedBigInteger('normalized_transaction_id');
            $table->json('data');
            $table->unique(['snapshot_id', 'side', 'normalized_transaction_id'], 'snapshot_row_identity');
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', fn (Blueprint $table) => $table->dropUnique('import_row_identity'));
        Schema::dropIfExists('unmatched_snapshot_rows');
        Schema::table('comparison_runs', fn (Blueprint $table) => $table->dropColumn(['file_totals', 'invalidated_at']));
        Schema::table('unmatched_snapshots', fn (Blueprint $table) => $table->dropColumn(['rows_persisted', 'file_totals']));
        Schema::table('imports', fn (Blueprint $table) => $table->dropColumn(['mapping_snapshot', 'mapping_hash', 'heartbeat_at', 'processing_file_hash']));
    }
};
