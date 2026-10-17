<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Existing rows are backfilled by 2026_10_17_000001_backfill_normalized_references. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('normalized_transactions', 'normalized_secondary_reference')) {
            Schema::table('normalized_transactions', function (Blueprint $table) {
                $table->string('normalized_secondary_reference')->nullable()->after('normalized_reference');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('normalized_transactions', 'normalized_secondary_reference')) {
            Schema::table('normalized_transactions', function (Blueprint $table) {
                $table->dropColumn('normalized_secondary_reference');
            });
        }
    }
};
