<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\MatchingStatus;
use App\Models\ExceptionRecord;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use App\Services\Matching\UnmatchedSweeper;

function makeSweepTx(Source $source, MatchingStatus $status): NormalizedTransaction
{
    $transaction = Transaction::factory()->create(['source_id' => $source->id]);

    return NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'matching_status' => $status->value,
    ]);
}

beforeEach(function () {
    $this->sweeper = new UnmatchedSweeper;
});

test('an unmatched row with no exception gets exactly one unmatched exception', function () {
    $source = Source::factory()->create();
    $nt = makeSweepTx($source, MatchingStatus::Unmatched);

    $created = $this->sweeper->sweep();

    expect($created)->toBe(1);
    $exception = ExceptionRecord::query()->sole();
    expect($exception->normalized_transaction_id)->toBe($nt->id);
    expect($exception->type)->toBe(ExceptionType::Unmatched);
    expect($exception->status)->toBe(ExceptionStatus::Open);
});

test('a matched row is never swept', function () {
    $source = Source::factory()->create();
    makeSweepTx($source, MatchingStatus::Matched);

    $created = $this->sweeper->sweep();

    expect($created)->toBe(0);
    expect(ExceptionRecord::query()->count())->toBe(0);
});

test('an unmatched row with an existing open exception is skipped', function () {
    $source = Source::factory()->create();
    $nt = makeSweepTx($source, MatchingStatus::Unmatched);
    ExceptionRecord::create([
        'normalized_transaction_id' => $nt->id,
        'type' => ExceptionType::Unmatched,
        'status' => ExceptionStatus::Open,
    ]);

    $created = $this->sweeper->sweep();

    expect($created)->toBe(0);
    expect(ExceptionRecord::query()->count())->toBe(1);
});

test('an unmatched row whose exception was resolved is not raised again', function () {
    $source = Source::factory()->create();
    $nt = makeSweepTx($source, MatchingStatus::Unmatched);
    ExceptionRecord::create([
        'normalized_transaction_id' => $nt->id,
        'type' => ExceptionType::Unmatched,
        'status' => ExceptionStatus::Resolved,
    ]);

    $created = $this->sweeper->sweep();

    expect($created)->toBe(0);
    expect(ExceptionRecord::query()->count())->toBe(1);
});

test('re-sweeping immediately is idempotent', function () {
    $source = Source::factory()->create();
    makeSweepTx($source, MatchingStatus::Unmatched);

    $this->sweeper->sweep();
    $firstCount = ExceptionRecord::query()->count();

    $secondCreated = $this->sweeper->sweep();

    expect($secondCreated)->toBe(0);
    expect(ExceptionRecord::query()->count())->toBe($firstCount);
});

test('unmatched preview is read-only and actual sweep respects file and date filters', function () {
    $source = Source::factory()->create();
    $import = \App\Models\Import::factory()->create(['source_id' => $source->id, 'status' => 'completed']);
    $otherImport = \App\Models\Import::factory()->create(['source_id' => $source->id, 'status' => 'completed']);
    $selected = makeSweepTx($source, MatchingStatus::Unmatched);
    $selected->transaction->update(['import_id' => $import->id]);
    $selected->update(['normalized_date' => '2026-05-15']);
    $outsideDate = makeSweepTx($source, MatchingStatus::Unmatched);
    $outsideDate->transaction->update(['import_id' => $import->id]);
    $outsideDate->update(['normalized_date' => '2026-06-15']);
    $outsideImport = makeSweepTx($source, MatchingStatus::Unmatched);
    $outsideImport->transaction->update(['import_id' => $otherImport->id]);
    $outsideImport->update(['normalized_date' => '2026-05-15']);

    expect($this->sweeper->previewCount($source->id, $import->id, '2026-05-01', '2026-05-31'))->toBe(1)
        ->and(ExceptionRecord::query()->count())->toBe(0);

    expect($this->sweeper->sweep($source->id, $import->id, '2026-05-01', '2026-05-31', 'preview-batch-002'))->toBe(1)
        ->and(ExceptionRecord::query()->sole()->normalized_transaction_id)->toBe($selected->id)
        ->and(ExceptionRecord::query()->sole()->batch_reference)->toBe('preview-batch-002');
});
