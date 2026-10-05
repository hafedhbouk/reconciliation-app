<?php

use App\Jobs\DetectDuplicatesJob;
use App\Jobs\NotifyMatchingBatchCompleteJob;
use App\Jobs\RunMatchingRuleJob;
use App\Jobs\SweepUnmatchedJob;
use App\Models\ExceptionRecord;
use App\Models\Import;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Support\Facades\Bus;

test('run dispatches RunMatchingRuleJob for the given rule with the triggering user for notification delivery', function () {
    $admin = actingAsAdmin();
    Bus::fake();
    $rule = MatchingRule::factory()->create();

    $this->post(route('admin.matching-rules.run', $rule))->assertRedirect(route('admin.matching-rules.index'));

    Bus::assertDispatched(RunMatchingRuleJob::class, fn ($job) => $job->matchingRuleId === $rule->id && $job->notifyUserId === $admin->id);
});

test('run-all dispatches a chain of active rules ordered by priority plus trailing sweep and notify jobs', function () {
    actingAsAdmin();
    Bus::fake();

    $firstRule = MatchingRule::factory()->create(['priority' => 5, 'is_active' => true]);
    $secondRule = MatchingRule::factory()->create(['priority' => 50, 'is_active' => true]);
    MatchingRule::factory()->create(['priority' => 1, 'is_active' => false]);

    $this->post(route('admin.matching-rules.run-all'))->assertRedirect(route('admin.matching-rules.index'));

    Bus::assertChained([
        RunMatchingRuleJob::class,
        RunMatchingRuleJob::class,
        DetectDuplicatesJob::class,
        SweepUnmatchedJob::class,
        NotifyMatchingBatchCompleteJob::class,
    ]);
});

test('a user without matching-rules.update cannot run a rule', function () {
    actingAsPlainUser();
    Bus::fake();
    $rule = MatchingRule::factory()->create();

    $this->post(route('admin.matching-rules.run', $rule))->assertForbidden();

    Bus::assertNotDispatched(RunMatchingRuleJob::class);
});

test('a user without matching-rules.update cannot trigger duplicate detection or the sweep', function () {
    actingAsPlainUser();
    Bus::fake();

    $this->post(route('admin.matching-rules.detect-duplicates'))->assertForbidden();
    $this->post(route('admin.matching-rules.sweep-unmatched'))->assertForbidden();

    Bus::assertNotDispatched(DetectDuplicatesJob::class);
    Bus::assertNotDispatched(SweepUnmatchedJob::class);
});

test('maintenance preview shows scoped counts without creating exceptions', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    $import = Import::factory()->create(['source_id' => $source->id, 'status' => 'completed']);
    $transaction = Transaction::factory()->create([
        'source_id' => $source->id,
        'import_id' => $import->id,
    ]);
    NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'normalized_date' => '2026-05-15',
    ]);

    $this->get(route('admin.matching-rules.maintenance', [
        'preview' => 1,
        'source_id' => $source->id,
        'import_id' => $import->id,
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-31',
    ]))
        ->assertOk()
        ->assertSee('Aperçu sans modification des données.')
        ->assertSee('1 transaction non rapprochée');

    expect(ExceptionRecord::query()->count())->toBe(0);
});

test('maintenance actions dispatch the previewed scope with a batch reference', function () {
    actingAsAdmin();
    Bus::fake();
    $source = Source::factory()->create();
    $import = Import::factory()->create(['source_id' => $source->id, 'status' => 'completed']);
    $filters = [
        'source_id' => $source->id,
        'import_id' => $import->id,
        'date_from' => '2026-05-01',
        'date_to' => '2026-05-31',
    ];

    $this->post(route('admin.matching-rules.detect-duplicates'), $filters)->assertRedirect();
    $this->post(route('admin.matching-rules.sweep-unmatched'), $filters)->assertRedirect();

    Bus::assertDispatched(DetectDuplicatesJob::class, fn ($job) =>
        $job->sourceId === $source->id
        && $job->importId === $import->id
        && $job->dateFrom === '2026-05-01'
        && $job->dateTo === '2026-05-31'
        && $job->batchReference !== null
    );
    Bus::assertDispatched(SweepUnmatchedJob::class, fn ($job) =>
        $job->sourceId === $source->id
        && $job->importId === $import->id
        && $job->dateFrom === '2026-05-01'
        && $job->dateTo === '2026-05-31'
        && $job->batchReference !== null
    );
});
