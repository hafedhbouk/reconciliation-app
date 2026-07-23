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
    $this->sweeper = new UnmatchedSweeper();
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

test('an unmatched row whose only exception was resolved gets swept again', function () {
    $source = Source::factory()->create();
    $nt = makeSweepTx($source, MatchingStatus::Unmatched);
    ExceptionRecord::create([
        'normalized_transaction_id' => $nt->id,
        'type' => ExceptionType::Unmatched,
        'status' => ExceptionStatus::Resolved,
    ]);

    $created = $this->sweeper->sweep();

    expect($created)->toBe(1);
    expect(ExceptionRecord::query()->count())->toBe(2);
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
