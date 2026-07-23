<?php

use App\Enums\ExceptionType;
use App\Models\ExceptionRecord;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use App\Services\Matching\DuplicateDetector;

function makeDedupTx(Source $source, string $hash): NormalizedTransaction
{
    $transaction = Transaction::factory()->create(['source_id' => $source->id]);

    return NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'dedup_hash' => $hash,
    ]);
}

beforeEach(function () {
    $this->detector = new DuplicateDetector();
});

test('a 2-row dedup group creates exactly 1 duplicate exception on the newer row', function () {
    $source = Source::factory()->create();
    $original = makeDedupTx($source, 'hash-a');
    $duplicate = makeDedupTx($source, 'hash-a');

    $summary = $this->detector->scan();

    expect($summary->groupsFound)->toBe(1);
    expect($summary->exceptionsCreated)->toBe(1);

    $exception = ExceptionRecord::query()->sole();
    expect($exception->type)->toBe(ExceptionType::Duplicate);
    expect($exception->normalized_transaction_id)->toBe($duplicate->id);
    expect($exception->normalized_transaction_id)->not->toBe($original->id);
});

test('a 3-row dedup group creates 2 duplicate exceptions', function () {
    $source = Source::factory()->create();
    makeDedupTx($source, 'hash-b');
    makeDedupTx($source, 'hash-b');
    makeDedupTx($source, 'hash-b');

    $summary = $this->detector->scan();

    expect($summary->groupsFound)->toBe(1);
    expect($summary->exceptionsCreated)->toBe(2);
    expect(ExceptionRecord::query()->where('type', ExceptionType::Duplicate->value)->count())->toBe(2);
});

test('rescanning is idempotent and creates no additional exceptions', function () {
    $source = Source::factory()->create();
    makeDedupTx($source, 'hash-c');
    makeDedupTx($source, 'hash-c');

    $this->detector->scan();
    $firstCount = ExceptionRecord::query()->count();

    $secondSummary = $this->detector->scan();

    expect(ExceptionRecord::query()->count())->toBe($firstCount);
    expect($secondSummary->exceptionsCreated)->toBe(0);
    expect($secondSummary->groupsFound)->toBe(1);
});

test('unique dedup hashes never produce an exception', function () {
    $source = Source::factory()->create();
    makeDedupTx($source, 'hash-unique-1');
    makeDedupTx($source, 'hash-unique-2');

    $summary = $this->detector->scan();

    expect($summary->groupsFound)->toBe(0);
    expect($summary->exceptionsCreated)->toBe(0);
    expect(ExceptionRecord::query()->count())->toBe(0);
});
