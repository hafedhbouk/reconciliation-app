<?php

use App\Jobs\ComputeUnmatchedJob;
use App\Jobs\RunAdHocMatchingJob;
use App\Jobs\RunMatchingRuleJob;
use App\Models\ExceptionRecord;
use App\Models\Import;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\SourceColumnMapping;
use App\Models\Transaction;
use App\Models\UnmatchedSnapshot;
use App\Models\User;
use App\Services\Import\MappingEngine;
use App\Services\Import\TransactionNormalizer;
use App\Services\Matching\RuleMatcher;
use Database\Seeders\BankSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\SourceColumnMappingSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Support\Facades\DB;

function fileComparisonImport(string $code): Import
{
    return Import::factory()->create(['source_id' => Source::factory()->create(['code' => $code])->id, 'status' => 'completed']);
}

function fileComparisonRow(Import $import, string $reference = '123456789', ?string $authorization = '001234', string $date = '2026-02-01', int $amount = 16000, string $status = 'unmatched'): NormalizedTransaction
{
    $authorizationField = in_array($import->source->code, ['WEB', 'STEG']) ? 'secondary_reference' : 'num_autorisation';
    $transaction = Transaction::factory()->create([
        'import_id' => $import->id,
        'source_id' => $import->source_id,
        'external_reference' => $reference,
        'raw_payload' => [$authorizationField => $authorization, 'reference' => $reference],
    ]);

    return NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'normalized_reference' => $reference,
        'normalized_date' => $date,
        'normalized_amount_millimes' => $amount,
        'matching_status' => $status,
    ]);
}

function runFileComparison(Import $a, Import $b, bool $reverse = false): UnmatchedSnapshot
{
    [$a, $b] = $reverse ? [$b, $a] : [$a, $b];
    (new RunAdHocMatchingJob($a->id, $b->id, 'file-test'))->handle(app(RuleMatcher::class));

    return UnmatchedSnapshot::where('import_a_id', $a->id)->where('import_b_id', $b->id)->sole();
}

test('all source pairs compare complete rows in either direction and isolate the selected files', function (string $codeA, string $codeB, bool $reverse) {
    $a = fileComparisonImport($codeA);
    $b = fileComparisonImport($codeB);
    // A stale global rule must not determine file-comparison fields.
    $oldRule = MatchingRule::factory()->create(['source_a_id' => $a->source_id, 'source_b_id' => $b->source_id]);
    $originalCriteria = $oldRule->criteria;
    $sameReference = $codeA === 'ALPHA' && in_array($codeB, ['WEB', 'STEG']);
    $exactA = fileComparisonRow($a, status: 'matched');
    $exactB = fileComparisonRow($b, reference: $sameReference ? '123456789' : '987654321');
    $onlyA = fileComparisonRow($a, '111111111', '111111', '2026-03-01', 25000, 'matched');
    $onlyB = fileComparisonRow($b, '222222222', '222222', '2026-03-02', 25000);
    $otherImport = Import::factory()->create(['source_id' => $b->source_id]);
    $outside = fileComparisonRow($otherImport, '111111111', '111111', '2026-03-01', 25000);

    $snapshot = runFileComparison($a, $b, $reverse);

    expect($snapshot->status)->toBe('completed');
    expect(array_column($snapshot->result_a, 'id'))->toBe([$reverse ? $onlyB->id : $onlyA->id]);
    expect(array_column($snapshot->result_b, 'id'))->toBe([$reverse ? $onlyA->id : $onlyB->id]);
    $result = MatchingResult::sole();
    expect($result->status->value)->toBe('matched');
    expect($result->matchingDetails->pluck('normalized_transaction_id')->all())->toEqualCanonicalizing([$exactA->id, $exactB->id]);
    expect($outside->fresh()->matching_status->value)->toBe('unmatched');
    expect($oldRule->fresh()->criteria)->toBe($originalCriteria);
    expect($result->matchingRule->is_active)->toBeFalse();
})->with([
    ['ALPHA', 'BNA'], ['ALPHA', 'WEB'], ['ALPHA', 'STEG'],
    ['ALPHA', 'SMT'], ['SMT', 'BNA'], ['WEB', 'SMT'], ['STEG', 'SMT'],
    ['WEB', 'BNA'], ['STEG', 'BNA'],
])->with([false, true]);

test('shared identifiers produce amount date and combined conflicts', function (string $codeA, string $codeB, bool $reverse) {
    $a = fileComparisonImport($codeA);
    $b = fileComparisonImport($codeB);
    foreach ([['100000001', '100001', '2026-02-01', 17000],
        ['100000002', '100002', '2026-02-02', 16000],
        ['100000003', '100003', '2026-02-02', 17000]] as [$reference, $authorization, $date, $amount]) {
        fileComparisonRow($a, $reference, $authorization);
        fileComparisonRow($b, $reference, $authorization, $date, $amount);
    }

    $snapshot = runFileComparison($a, $b, $reverse);

    expect(MatchingResult::where('status', 'conflict')->count())->toBe(3);
    expect(ExceptionRecord::pluck('type')->map(fn ($type) => $type->value)->all())
        ->toEqualCanonicalizing(['amount_mismatch', 'date_mismatch', 'conflict']);
    expect($snapshot->result_a)->toBe([]);
    expect($snapshot->result_b)->toBe([]);
})->with([['ALPHA', 'BNA'], ['ALPHA', 'WEB'], ['ALPHA', 'STEG'], ['WEB', 'BNA'], ['STEG', 'BNA']])->with([false, true]);

test('Alpha WEB receipt discrepancies are conflicts and exact rows are removed first', function (string $webCode, bool $reverse) {
    $a = fileComparisonImport('ALPHA');
    $b = fileComparisonImport($webCode);
    $exactA = fileComparisonRow($a);
    $exactB = fileComparisonRow($b);
    $conflictA = fileComparisonRow($a, authorization: '001235');
    $conflictB = fileComparisonRow($b, authorization: '001236');

    runFileComparison($a, $b, $reverse);

    expect(MatchingResult::where('status', 'matched')->sole()->matchingDetails->pluck('normalized_transaction_id')->all())
        ->toEqualCanonicalizing([$exactA->id, $exactB->id]);
    expect(MatchingResult::where('status', 'conflict')->sole()->matchingDetails->pluck('normalized_transaction_id')->all())
        ->toEqualCanonicalizing([$conflictA->id, $conflictB->id]);
    expect(ExceptionRecord::sole()->type->value)->toBe('conflict');
})->with(['WEB', 'STEG'])->with([false, true]);

test('extra duplicate occurrences remain on their own side', function (string $codeB) {
    $a = fileComparisonImport('ALPHA');
    $b = fileComparisonImport($codeB);
    fileComparisonRow($a);
    $extra = fileComparisonRow($a);
    fileComparisonRow($b);

    $snapshot = runFileComparison($a, $b);

    expect(MatchingResult::sole()->matchingDetails)->toHaveCount(2);
    expect(array_column($snapshot->result_a, 'id'))->toBe([$extra->id]);
    expect($snapshot->result_b)->toBe([]);
})->with(['BNA', 'SMT', 'WEB']);

test('equal sums cannot replace equality of individual rows', function () {
    $a = fileComparisonImport('ALPHA');
    $b = fileComparisonImport('BNA');
    fileComparisonRow($a, amount: 10000);
    fileComparisonRow($a, amount: 20000);
    fileComparisonRow($b, amount: 30000);

    runFileComparison($a, $b);

    expect(MatchingResult::sole()->status->value)->toBe('conflict');
});

test('missing identifiers do not link unrelated rows', function () {
    $a = fileComparisonImport('ALPHA');
    $b = fileComparisonImport('BNA');
    fileComparisonRow($a, authorization: null);
    fileComparisonRow($b, authorization: null);

    $snapshot = runFileComparison($a, $b);

    expect(MatchingResult::count())->toBe(0);
    expect($snapshot->result_a)->toHaveCount(1);
    expect($snapshot->result_b)->toHaveCount(1);
});

test('numeric identifiers with different leading zeros remain distinct without losing rows', function (bool $reverse) {
    $a = fileComparisonImport('ALPHA');
    $b = fileComparisonImport('WEB');
    $onlyA = fileComparisonRow($a, reference: '000123456');
    $onlyB = fileComparisonRow($b, reference: '123456');

    $snapshot = runFileComparison($a, $b, $reverse);

    expect(MatchingResult::count())->toBe(0);
    expect(array_column($snapshot->result_a, 'id'))->toBe([$reverse ? $onlyB->id : $onlyA->id]);
    expect(array_column($snapshot->result_b, 'id'))->toBe([$reverse ? $onlyA->id : $onlyB->id]);
})->with([false, true]);

test('refreshing differences preserves surplus rows without creating matches or changing statuses', function () {
    $a = fileComparisonImport('ALPHA');
    $b = fileComparisonImport('WEB');
    fileComparisonRow($a);
    fileComparisonRow($a);
    fileComparisonRow($b);
    $snapshot = runFileComparison($a, $b);
    $expectedA = $snapshot->result_a;
    $statuses = NormalizedTransaction::pluck('matching_status', 'id')->all();
    $resultsCount = MatchingResult::count();

    (new ComputeUnmatchedJob($snapshot->id))->handle();

    expect($snapshot->fresh()->result_a)->toBe($expectedA);
    expect($snapshot->fresh()->result_b)->toBe([]);
    expect(MatchingResult::count())->toBe($resultsCount);
    expect(NormalizedTransaction::pluck('matching_status', 'id')->all())->toBe($statuses);
});

test('the individual rule job uses the same file comparison when two imports are supplied', function () {
    $a = fileComparisonImport('ALPHA');
    $b = fileComparisonImport('WEB');
    $rule = MatchingRule::factory()->create(['source_a_id' => $a->source_id, 'source_b_id' => $b->source_id]);
    fileComparisonRow($a);
    fileComparisonRow($b, date: '2026-03-01', amount: 18000);

    (new RunMatchingRuleJob($rule->id, 'individual-file-test', null, $a->id, $b->id))->handle(app(RuleMatcher::class));

    expect(MatchingResult::sole()->status->value)->toBe('conflict');
    expect(UnmatchedSnapshot::sole()->result_a)->toBe([]);
});

test('SMT date-only and amount-only similarities remain unmatched', function () {
    $a = fileComparisonImport('SMT');
    $b = fileComparisonImport('BNA');
    fileComparisonRow($a);
    fileComparisonRow($b, amount: 17000);
    fileComparisonRow($b, date: '2026-02-02');

    $snapshot = runFileComparison($a, $b);

    expect(MatchingResult::count())->toBe(0);
    expect($snapshot->result_a)->toHaveCount(1);
    expect($snapshot->result_b)->toHaveCount(2);
});

test('the six combinations work with the actual seeded source mappings and normalization', function () {
    $this->seed([
        CurrencySeeder::class,
        BankSeeder::class,
        SourceSeeder::class,
        SourceColumnMappingSeeder::class,
    ]);
    $user = User::factory()->create();
    $rawRows = [
        'ALPHA' => ['REFERENCE' => 1234567, 'NUM_AUTO' => 'b3512', 'DAT_ENC' => '01/02/2026', 'MONTANT_ENCAISS' => '000000016000'],
        'BNA' => ['N° autorisation' => 3512, 'Date' => '01/02/2026', 'Montant (TND)' => '16.000'],
        'WEB' => ['reference' => '001234567', 'recu_paie' => 'B003512', 'date_paiement' => '2026-02-01 12:34:56', 'montant' => '000000016000'],
        'SMT' => ['New Deposit date' => '2026.02.01 23:59:59', 'Montant' => '16.000', 'Date' => '2026.01.31 08:00:00'],
    ];
    $imports = [];
    foreach ($rawRows as $code => $raw) {
        $source = Source::where('code', $code)->sole();
        $import = Import::factory()->create(['source_id' => $source->id, 'status' => 'completed']);
        $mapped = app(MappingEngine::class)->transformRow($raw,
            SourceColumnMapping::where('source_id', $source->id)->orderBy('sort_order')->get());
        $normalizer = app(TransactionNormalizer::class);
        $transaction = $normalizer->buildTransactionRow($mapped, $source, $import, $user->id);
        $transactionId = DB::table('transactions')->insertGetId($transaction);
        DB::table('normalized_transactions')->insert(
            $normalizer->buildNormalizedRow($transactionId, $normalizer->computeNormalizedSnapshot($transaction), $user->id));
        $imports[$code] = $import;
    }

    foreach ([['ALPHA', 'BNA'], ['ALPHA', 'WEB'], ['ALPHA', 'SMT'], ['SMT', 'BNA'], ['WEB', 'SMT'], ['WEB', 'BNA']] as [$a, $b]) {
        $snapshot = runFileComparison($imports[$a], $imports[$b]);
        expect($snapshot->result_a)->toBe([]);
        expect($snapshot->result_b)->toBe([]);
    }
    expect(MatchingResult::where('status', 'matched')->count())->toBe(6);
    expect(MatchingResult::where('status', 'conflict')->count())->toBe(0);
});
