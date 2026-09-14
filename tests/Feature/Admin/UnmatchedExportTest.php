<?php

use App\Jobs\GenerateMatchingExportJob;
use App\Models\Import;
use App\Models\MatchingExport;
use App\Models\UnmatchedSnapshot;
use App\Services\Matching\SnapshotRows;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\IOFactory;

function exportSnapshot(int $count = 2): UnmatchedSnapshot
{
    $snapshot = UnmatchedSnapshot::create([
        'import_a_id' => Import::factory()->create()->id,
        'import_b_id' => Import::factory()->create()->id,
        'status' => 'completed', 'completed_at' => now(), 'rows_persisted' => true,
    ]);
    foreach (['a', 'b'] as $side) {
        app(SnapshotRows::class)->insert($snapshot->id, $side, array_map(fn ($id) => [
            'id' => $id, 'source' => strtoupper($side), 'reference' => '000123',
            'amount_millimes' => 123456, 'date' => '14/09/2026',
        ], range(1, $count)));
    }

    return $snapshot;
}

function queuedUnmatchedExport(UnmatchedSnapshot $snapshot, string $format): MatchingExport
{
    $export = MatchingExport::create([
        'user_id' => auth()->id(), 'format' => $format, 'status' => 'pending',
        'filters' => ['type' => 'unmatched', 'snapshot_id' => $snapshot->id],
        'download_token' => str()->random(64),
    ]);
    (new GenerateMatchingExportJob($export))->handle();

    return $export->refresh();
}

test('unmatched export is queued and listed with the matching exports', function () {
    actingAsAdmin();
    Queue::fake();
    $snapshot = exportSnapshot();

    $this->post(route('admin.reconciliation.unmatched.export-async', $snapshot), ['format' => 'csv'])
        ->assertRedirect(route('admin.matching-results.exports'));

    $export = MatchingExport::query()->sole();
    expect($export->filters)->toMatchArray(['type' => 'unmatched', 'snapshot_id' => $snapshot->id]);
    Queue::assertPushed(GenerateMatchingExportJob::class);
});

test('worker exports both sides beyond the displayed page', function () {
    actingAsAdmin();
    $snapshot = exportSnapshot(501);
    $export = queuedUnmatchedExport($snapshot, 'csv');
    $response = $this->get(route('admin.matching-results.exports.download', $export->download_token));
    $response->assertOk()->assertDownload('differences.csv');
    $lines = explode("\n", trim(file_get_contents($response->baseResponse->getFile()->getPathname())));
    expect($lines)->toHaveCount(1003)
        ->and($lines[1])->toContain('000123', '123456')
        ->and($lines[1002])->toStartWith('"B";');
});

test('worker excel preserves reference text and pdf produces a readable document', function () {
    actingAsAdmin();
    $snapshot = exportSnapshot();
    $excel = $this->get(route('admin.matching-results.exports.download', queuedUnmatchedExport($snapshot, 'xlsx')->download_token));
    $excel->assertOk()->assertDownload('differences.xlsx');
    $book = IOFactory::load($excel->baseResponse->getFile()->getPathname());
    expect($book->getActiveSheet()->getCell('F2')->getValue())->toBe('000123')
        ->and($book->getActiveSheet()->getHighestRow())->toBe(5);
    $book->disconnectWorksheets();
    $pdf = $this->get(route('admin.matching-results.exports.download', queuedUnmatchedExport($snapshot, 'pdf')->download_token));
    $pdf->assertOk()->assertDownload('differences.pdf');
    expect(file_get_contents($pdf->baseResponse->getFile()->getPathname()))->toStartWith('%PDF-');
});

test('queued exports reject unauthorized users and incomplete comparisons', function () {
    actingAsPlainUser();
    $snapshot = exportSnapshot();
    $this->post(route('admin.reconciliation.unmatched.export-async', $snapshot), ['format' => 'csv'])->assertForbidden();
    actingAsAdmin();
    $snapshot->update(['status' => 'failed']);
    $this->post(route('admin.reconciliation.unmatched.export-async', $snapshot), ['format' => 'csv'])->assertStatus(409);
    $snapshot->update(['status' => 'completed']);
    $this->post(route('admin.reconciliation.unmatched.export-async', $snapshot), ['format' => 'exe'])->assertSessionHasErrors('format');
});

test('large pdf and excel exports report the limit instead of truncating results', function () {
    actingAsAdmin();
    $snapshot = exportSnapshot(501);
    foreach (['pdf', 'xlsx'] as $format) {
        $this->post(route('admin.reconciliation.unmatched.export-async', $snapshot), ['format' => $format])
            ->assertRedirect()->assertSessionHas('export_error');
    }
});

test('legacy results can be exported with safe spreadsheet text and signed millimes', function () {
    actingAsAdmin();
    $snapshot = UnmatchedSnapshot::create([
        'import_a_id' => Import::factory()->create()->id,
        'import_b_id' => Import::factory()->create()->id,
        'status' => 'completed', 'completed_at' => now(),
        'result_a' => [['id' => 1, 'source' => 'ALPHA', 'reference' => '=1+1', 'amount_millimes' => -123456]],
        'result_b' => [],
    ]);
    $this->get(route('admin.reconciliation.unmatched', [
        'import_a_id' => $snapshot->import_a_id, 'import_b_id' => $snapshot->import_b_id,
    ]))->assertOk()->assertSee(route('admin.reconciliation.unmatched.export-async', $snapshot));
    $export = queuedUnmatchedExport($snapshot, 'csv');
    $response = $this->get(route('admin.matching-results.exports.download', $export->download_token));
    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toContain("'=1+1", '"-123456"');
});
