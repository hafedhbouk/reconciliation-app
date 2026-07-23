<?php

use Illuminate\Support\Facades\DB;

/**
 * Regression guard against future N+1 reintroduction, informed by the
 * Phase 5 performance audit's confirmed-clean N+1 finding: every DataTables
 * data() action and show() method already eager-loads exactly what it
 * touches. A budget (not an exact count) tolerates minor incidental query
 * growth without breaking on every unrelated change.
 */
test('the search datatables endpoint stays within a bounded query budget', function () {
    actingAsAdmin();
    $source = \App\Models\Source::factory()->create();
    \App\Models\NormalizedTransaction::factory()->count(10)->create([
        'transaction_id' => fn () => \App\Models\Transaction::factory()->create(['source_id' => $source->id])->id,
    ]);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    $this->getJson(route('admin.search.data'))->assertOk();

    expect($queryCount)->toBeLessThan(15);
});

test('the dashboard stays within a bounded query budget', function () {
    actingAsAdmin();
    \App\Models\Transaction::factory()->count(5)->create();

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    $this->get(route('dashboard'))->assertOk();

    expect($queryCount)->toBeLessThan(20);
});
