<?php

use App\Enums\ExceptionType;
use App\Models\ExceptionRecord;
use App\Models\Import;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use App\Services\Import\TransactionNormalizer;
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
    $this->detector = new DuplicateDetector;
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

test('BNA authorizations distinguish legacy collisions and detect duplicates across hash versions', function () {
    $source = Source::factory()->create(['code' => 'BNA']);
    foreach ([['001111', 'old-hash'], ['002222', 'old-hash'], ['001111', 'new-hash']] as [$authorization, $hash]) {
        $row = makeDedupTx($source, $hash);
        $row->update(['normalized_date' => '2026-05-01', 'normalized_amount_millimes' => 10000]);
        $row->transaction->update(['external_reference' => null, 'raw_payload' => ['num_autorisation' => $authorization]]);
    }
    $summary = $this->detector->scan();
    expect($summary->groupsFound)->toBe(1);
    expect($summary->exceptionsCreated)->toBe(1);
    expect(ExceptionRecord::sole()->normalized_transaction_id)->toBe($row->id);
});

test('deleted imports and deleted transactions are excluded from duplicate scans', function () {
    $source = Source::factory()->create();
    makeDedupTx($source, 'same');
    $archived = makeDedupTx($source, 'same');
    $import = Import::factory()->create(['source_id' => $source->id]);
    $archived->transaction->update(['import_id' => $import->id]);
    $import->delete();
    $deleted = makeDedupTx($source, 'same');
    $deleted->transaction->delete();

    expect($this->detector->scan()->exceptionsCreated)->toBe(0);
});

test('BNA normalized hashes include authorization', function () {
    $normalizer = app(TransactionNormalizer::class);
    $base = ['source_id' => 1, 'external_reference' => null, 'transaction_date' => '2026-05-01', 'amount_millimes' => 10000];
    $a = $normalizer->computeNormalizedSnapshot($base + ['raw_payload' => json_encode(['num_autorisation' => '001111'])]);
    $b = $normalizer->computeNormalizedSnapshot($base + ['raw_payload' => json_encode(['num_autorisation' => '002222'])]);
    expect($a['dedup_hash'])->not->toBe($b['dedup_hash']);
});

test('SMT duplicate alerts remain explicitly potential duplicates', function () {
    $source = Source::factory()->create(['code' => 'SMT']);
    makeDedupTx($source, 'same');
    makeDedupTx($source, 'same');
    $this->detector->scan();
    expect(ExceptionRecord::sole()->resolution_comment)->toContain('Doublon potentiel');
});
