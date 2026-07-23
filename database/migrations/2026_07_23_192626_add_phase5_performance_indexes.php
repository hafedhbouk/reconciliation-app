<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes added based on real query patterns measured across Phases 3-4,
 * not speculatively:
 * - normalized_transactions.matching_status is filtered by RuleMatcher on
 *   every rule run (twice, once per side) against a ~80k-row per-source
 *   pool, and by SearchController on every page load.
 * - normalized_transactions.normalized_amount_millimes / .normalized_date
 *   are range-filtered directly by SearchController.
 * - matching_results.status is grouped by DashboardMetricsService against
 *   a table already at ~150k rows after only 2 of 6 rules ran once.
 * - exceptions.type / .status are added pre-emptively -- cheap now, the
 *   table only grows over time via UnmatchedSweeper.
 *
 * Deliberately NOT indexed: transactions.canal (SearchController filters it
 * with a leading-wildcard LIKE, which cannot use a btree index regardless --
 * the index would be dead weight) and imports.status (current import volume
 * doesn't warrant it; revisit if that changes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('normalized_transactions', function (Blueprint $table) {
            $table->index('matching_status');
            $table->index('normalized_amount_millimes');
            $table->index('normalized_date');
        });

        Schema::table('matching_results', function (Blueprint $table) {
            $table->index('status');
        });

        Schema::table('exceptions', function (Blueprint $table) {
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('normalized_transactions', function (Blueprint $table) {
            $table->dropIndex(['matching_status']);
            $table->dropIndex(['normalized_amount_millimes']);
            $table->dropIndex(['normalized_date']);
        });

        Schema::table('matching_results', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('exceptions', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
        });
    }
};
