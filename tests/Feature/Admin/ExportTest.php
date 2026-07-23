<?php

use App\Exports\GenericTableExport;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use Maatwebsite\Excel\Facades\Excel;

function makeExportTx(Source $source, string $reference): NormalizedTransaction
{
    $transaction = Transaction::factory()->create(['source_id' => $source->id, 'external_reference' => $reference]);

    return NormalizedTransaction::factory()->create(['transaction_id' => $transaction->id, 'normalized_reference' => $reference]);
}

test('search export downloads csv, xlsx and pdf', function () {
    Excel::fake();
    actingAsAdmin();
    $source = Source::factory()->create();
    makeExportTx($source, '111111');

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->get(route('admin.search.export', $format))->assertOk();
        Excel::assertDownloaded("recherche.{$format}", fn (GenericTableExport $export) => true);
    }
});

test('search export rejects an invalid format', function () {
    Excel::fake();
    actingAsAdmin();

    $this->get(route('admin.search.export', 'docx'))->assertNotFound();
});

test('search export is forbidden without search.viewAny', function () {
    Excel::fake();
    actingAsPlainUser();

    $this->get(route('admin.search.export', 'csv'))->assertForbidden();
});

test('pdf and xlsx exports are capped at 1000 rows, csv is not', function () {
    // Real-data manual verification found XLSX (PhpSpreadsheet) and PDF
    // (dompdf) both build a full in-memory object model and exhaust PHP's
    // memory limit on a ~150k-row export; only CSV streams row-by-row.
    Excel::fake();
    actingAsAdmin();
    $source = Source::factory()->create();
    NormalizedTransaction::factory()->count(1005)->create([
        'transaction_id' => fn () => Transaction::factory()->create(['source_id' => $source->id])->id,
    ]);

    $this->get(route('admin.search.export', 'pdf'))->assertOk();
    $this->get(route('admin.search.export', 'xlsx'))->assertOk();
    $this->get(route('admin.search.export', 'csv'))->assertOk();

    // ->count() ignores LIMIT (Laravel strips it for aggregate queries),
    // so assert on the actual fetched row count instead.
    Excel::assertDownloaded('recherche.pdf', fn (GenericTableExport $export) => $export->query()->get()->count() === 1000);
    Excel::assertDownloaded('recherche.xlsx', fn (GenericTableExport $export) => $export->query()->get()->count() === 1000);
    Excel::assertDownloaded('recherche.csv', fn (GenericTableExport $export) => $export->query()->get()->count() === 1005);
});

test('exceptions export downloads csv, xlsx and pdf', function () {
    Excel::fake();
    actingAsAdmin();
    \App\Models\ExceptionRecord::factory()->create();

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->get(route('admin.exceptions.export', $format))->assertOk();
        Excel::assertDownloaded("exceptions.{$format}", fn (GenericTableExport $export) => true);
    }
});

test('matching-results export downloads csv, xlsx and pdf', function () {
    Excel::fake();
    actingAsAdmin();
    \App\Models\MatchingResult::factory()->create();

    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $this->get(route('admin.matching-results.export', $format))->assertOk();
        Excel::assertDownloaded("matching-results.{$format}", fn (GenericTableExport $export) => true);
    }
});
