<?php

use App\Exceptions\UnmatchedExportUnavailableException;
use App\Jobs\ComputeUnmatchedJob;
use App\Jobs\GenerateMatchingExportJob;
use App\Jobs\ProcessImportJob;
use App\Jobs\RunAdHocMatchingJob;
use App\Models\Import;
use App\Models\MatchingExport;
use App\Models\NormalizedTransaction;
use App\Models\Operation;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Models\Transaction;
use App\Models\UnmatchedSnapshot;
use App\Models\User;
use App\Services\Import\MappingEngine;
use App\Services\Import\Readers\ImportRowReaderFactory;
use App\Services\Import\TransactionNormalizer;
use App\Services\Matching\RuleMatcher;
use App\Services\Matching\SnapshotRows;
use App\Services\OperationTracker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/** Import CSV de trois lignes (dont une rejetée : référence vide), prêt à être traité. */
function trackedCsvImport(string $csv = "ref,amount,date\nfirst,1000,2026-05-01\n,2000,2026-05-01\nlast,3000,2026-05-01\n"): Import
{
    Storage::fake('local');
    $source = Source::factory()->create(['file_type' => 'csv']);
    foreach (['reference' => 'ref', 'amount' => 'amount', 'date' => 'date'] as $target => $column) {
        SourceColumnMapping::create(['source_id' => $source->id, 'target_field' => $target,
            'source_column' => $column, 'is_required' => true, 'transform' => []]);
    }
    Storage::put('imports/tracked.csv', $csv);

    return Import::factory()->create(['source_id' => $source->id, 'stored_path' => 'imports/tracked.csv',
        'file_hash' => hash('sha256', $csv), 'status' => 'pending', 'imported_by' => User::factory()->create()->id]);
}

/** Import « terminé » d'une source donnée, avec une transaction par autorisation. */
function trackedComparisonImport(string $code, array $authorizations): Import
{
    $import = Import::factory()->create(['source_id' => Source::factory()->create(['code' => $code])->id, 'status' => 'completed']);
    foreach ($authorizations as $authorization) {
        $transaction = Transaction::factory()->create([
            'import_id' => $import->id, 'source_id' => $import->source_id,
            'external_reference' => $code === 'BNA' ? null : '123456789',
            'raw_payload' => ['num_autorisation' => $authorization],
        ]);
        NormalizedTransaction::factory()->create([
            'transaction_id' => $transaction->id,
            'normalized_reference' => $code === 'BNA' ? $authorization : '123456789',
            'normalized_date' => '2026-07-01', 'normalized_amount_millimes' => 10000,
        ]);
    }

    return $import;
}

function runTrackedImport(Import $import, ?int $operationId = null): void
{
    (new ProcessImportJob($import->id, $operationId))->handle(
        app(ImportRowReaderFactory::class),
        app(MappingEngine::class),
        app(TransactionNormalizer::class),
    );
}

test('the percentage only comes from real counts', function (array $attributes, ?int $expected) {
    expect((new Operation($attributes + ['processed' => 0, 'status' => 'running']))->percent())->toBe($expected);
})->with([
    'unknown total' => [['processed' => 40, 'total' => null], null],
    'empty total' => [['processed' => 0, 'total' => 0], null],
    'partial' => [['processed' => 1250, 'total' => 5000], 25],
    'never 100 % while running' => [['processed' => 5000, 'total' => 5000], 99],
    'unmeasurable stage' => [['processed' => 10, 'total' => 20, 'indeterminate' => true], null],
    'completed' => [['processed' => 3, 'total' => 3, 'status' => 'completed'], 100],
]);

test('the lifecycle records real progress and never turns a failure into a success', function () {
    $tracker = app(OperationTracker::class);
    $operation = $tracker->create('import', 'Import test.csv', User::factory()->create()->id);
    expect($operation->fresh()->status)->toBe('pending');

    $tracker->start($operation, 5000, 'Lecture');
    $tracker->advance($operation, 1250, force: true);
    // Moins d'une seconde après : pas de nouvelle écriture (sauf $force).
    $tracker->advance($operation, 1300);
    expect($operation->fresh())->status->toBe('running')->processed->toBe(1250)->total->toBe(5000)
        ->and($operation->fresh()->percent())->toBe(25);

    $tracker->fail($operation, 'Fichier illisible');
    $tracker->finish($operation, ['lignes' => 1]);
    expect($operation->fresh())->status->toBe('failed')->error_message->toBe('Fichier illisible')
        ->and($operation->fresh()->finished_at)->not->toBeNull();
});

test('a running job without news for five minutes is reported as stale', function () {
    $operation = Operation::create(['uuid' => (string) str()->uuid(), 'type' => 'import', 'label' => 'x',
        'status' => 'running', 'started_at' => now()->subMinutes(20), 'heartbeat_at' => now()->subMinutes(6)]);

    expect($operation->isStale())->toBeTrue()
        ->and($operation->toProgressArray()['stale'])->toBeTrue();
});

test('progress is written on a separate connection when the database is not sqlite', function () {
    $tracker = app(OperationTracker::class);

    // Aucune connexion n'est ouverte : on vérifie seulement la configuration choisie.
    expect($tracker->connectionFor('mysql'))->toBe(OperationTracker::CONNECTION)
        ->and(config('database.connections.'.OperationTracker::CONNECTION.'.driver'))->toBe('mysql')
        ->and($tracker->connection())->toBe(config('database.default')); // SQLite en tests : même connexion
});

test('an import goes from pending to completed with the file row count as total', function () {
    $import = trackedCsvImport();
    $operation = app(OperationTracker::class)->create('import', 'Import tracked.csv', $import->imported_by, $import);

    runTrackedImport($import, $operation->id);

    expect($operation->fresh())
        ->status->toBe('completed')->total->toBe(3)->processed->toBe(3)->succeeded->toBe(2)->failed->toBe(1)
        ->summary->toBe(['acceptees' => 2, 'rejetees' => 1])
        ->result_url->toBe(route('admin.imports.show', $import, false));
});

test('an import rejected for missing columns is recorded as failed', function () {
    $import = trackedCsvImport("other,amount,date\nx,1000,2026-05-01\n");

    runTrackedImport($import);

    $operation = Operation::sole();
    expect($operation)->status->toBe('failed')->type->toBe('import')->user_id->toBe($import->imported_by)
        ->and($operation->error_message)->toContain('Colonnes requises manquantes');
});

test('a file comparison counts the rows of both files and keeps the result link', function () {
    $alpha = trackedComparisonImport('ALPHA', ['000001', '000002']);
    $bna = trackedComparisonImport('BNA', ['000001', '000003', '000004']);
    $user = User::factory()->create();
    $operation = app(OperationTracker::class)->create('matching', 'Rapprochement', $user->id);

    (new RunAdHocMatchingJob($alpha->id, $bna->id, 'tracked-batch', $user->id, $operation->id))->handle(app(RuleMatcher::class));

    expect($operation->fresh())->status->toBe('completed')->total->toBe(5)->processed->toBe(5)
        ->summary->toBe(['rapprochements' => 1, 'conflits' => 0, 'exclusives_a' => 1, 'exclusives_b' => 2])
        ->result_url->toBe(route('admin.reconciliation.unmatched', ['import_a_id' => $alpha->id, 'import_b_id' => $bna->id], false));
});

test('the differences computation is tracked and a failure is reported', function (bool $fails) {
    $alpha = trackedComparisonImport('ALPHA', ['000001']);
    $bna = trackedComparisonImport('BNA', ['000001']);
    $snapshot = UnmatchedSnapshot::create(['import_a_id' => $alpha->id, 'import_b_id' => $bna->id, 'status' => 'pending']);
    if ($fails) {
        $bna->source->update(['code' => 'UNKNOWN']); // couple de sources non pris en charge
    }

    (new ComputeUnmatchedJob($snapshot->id))->handle();

    $operation = Operation::sole();
    expect($operation)->type->toBe('comparison')->subject_id->toBe($snapshot->id)
        ->status->toBe($fails ? 'failed' : 'completed');
    if ($fails) {
        expect($operation->error_message)->toContain('Comparaison interrompue');
    } else {
        expect($operation->processed)->toBe(2)->and($operation->summary['rapprochees'])->toBe(1);
    }
})->with([false, true]);

test('a differences export counts the written rows, and an unavailable comparison fails the export', function (bool $available) {
    $snapshot = UnmatchedSnapshot::create([
        'import_a_id' => Import::factory()->create()->id, 'import_b_id' => Import::factory()->create()->id,
        'status' => $available ? 'completed' : 'failed', 'completed_at' => now(), 'rows_persisted' => true,
    ]);
    app(SnapshotRows::class)->insert($snapshot->id, 'a', [
        ['id' => 1, 'source' => 'A', 'reference' => 'x', 'amount_millimes' => 1, 'date' => '01/07/2026'],
        ['id' => 2, 'source' => 'A', 'reference' => 'y', 'amount_millimes' => 2, 'date' => '01/07/2026'],
    ]);
    $export = MatchingExport::create(['user_id' => User::factory()->create()->id, 'format' => 'csv', 'status' => 'pending',
        'filters' => ['type' => 'unmatched', 'snapshot_id' => $snapshot->id], 'download_token' => str()->random(64)]);

    $job = fn () => (new GenerateMatchingExportJob($export))->handle();
    $available ? $job() : expect($job)->toThrow(UnmatchedExportUnavailableException::class);

    expect(Operation::sole())->type->toBe('export')->user_id->toBe($export->user_id)
        ->status->toBe($available ? 'completed' : 'failed')
        ->processed->toBe($available ? 2 : 0);
})->with([true, false]);

test('launching a comparison creates a pending operation for the user and hands it to the job', function () {
    Queue::fake();
    $user = actingAsAdmin();
    $alpha = trackedComparisonImport('ALPHA', ['000001']);
    $bna = trackedComparisonImport('BNA', ['000001']);

    $this->post(route('admin.matching-rules.run-ad-hoc'), ['import_a_id' => $alpha->id, 'import_b_id' => $bna->id])->assertRedirect();

    $operation = Operation::sole();
    expect($operation)->status->toBe('pending')->type->toBe('matching')->user_id->toBe($user->id);
    Queue::assertPushed(RunAdHocMatchingJob::class, fn ($job) => $job->operationId === $operation->id);
});

test('each user only sees their own operations, administrators can see all', function () {
    $tracker = app(OperationTracker::class);
    $owner = User::factory()->create();
    $mine = $tracker->create('import', 'Mon import', $owner->id);
    $other = $tracker->create('export', 'Export d’un collègue', User::factory()->create()->id);

    $this->actingAs($owner);
    $this->getJson(route('admin.operations.show', $mine))->assertOk()->assertJsonPath('label', 'Mon import');
    $this->getJson(route('admin.operations.show', $other))->assertForbidden();
    // ?all=1 est ignoré sans les droits d'administrateur.
    $this->getJson(route('admin.operations.data', ['all' => 1]))->assertOk()
        ->assertJsonCount(1, 'operations')->assertJsonPath('operations.0.uuid', $mine->uuid)->assertJsonPath('active_count', 1);

    actingAsAdmin();
    $this->getJson(route('admin.operations.show', $other))->assertOk();
    $this->getJson(route('admin.operations.data', ['all' => 1]))->assertOk()->assertJsonCount(2, 'operations');
});

test('simultaneous operations keep their own progress', function () {
    $tracker = app(OperationTracker::class);
    $import = $tracker->create('import', 'Import A', null);
    $matching = $tracker->create('matching', 'Rapprochement B', null);
    $tracker->start($import, 100);
    $tracker->start($matching, 50);

    $tracker->advance($import, 45, force: true);
    $tracker->advance($matching, 36, force: true);
    $tracker->finish($matching);

    expect($import->fresh())->status->toBe('running')->processed->toBe(45)
        ->and($import->fresh()->percent())->toBe(45)
        ->and($matching->fresh())->status->toBe('completed')->processed->toBe(36);
});

test('the operations page lists the operations after a reload', function () {
    $user = actingAsPlainUser();
    $operation = app(OperationTracker::class)->create('import', 'Import BNA-07-2026.xlsx', $user->id);
    app(OperationTracker::class)->start($operation, 79718);

    $this->get(route('admin.operations.index'))->assertOk()
        ->assertSee('Mes opérations')
        ->assertSee('data-operations-list', false)
        ->assertSee($operation->uuid);
});
