<?php

use App\Enums\ExceptionType;
use App\Enums\MatchingResultStatus;
use App\Models\ExceptionRecord;
use App\Models\MatchingResult;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use App\Services\Matching\ConfidenceScorer;
use App\Services\Matching\RuleMatcher;
use Illuminate\Support\Str;

function makeNormalizedTx(Source $source, string $reference, int $amountMillimes, string $date, ?string $statusRaw = null): NormalizedTransaction
{
    $transaction = Transaction::factory()->create([
        'source_id' => $source->id,
        'external_reference' => $reference,
        'amount_millimes' => $amountMillimes,
        'transaction_date' => $date,
        'raw_payload' => $statusRaw !== null ? ['status_raw' => $statusRaw] : [],
    ]);

    return \App\Models\NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'normalized_reference' => $reference,
        'normalized_amount_millimes' => $amountMillimes,
        'normalized_date' => $date,
        'dedup_hash' => hash('sha256', Str::random(32)),
    ]);
}

beforeEach(function () {
    $this->matcher = new RuleMatcher(new ConfidenceScorer());
});

test('exact match on both amount and date creates a Matched result with full confidence', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    $ntA = makeNormalizedTx($sourceA, '843500', 1773000, '2026-01-15');
    $ntB = makeNormalizedTx($sourceB, '843500', 1773000, '2026-01-15');

    $rule = \App\Models\MatchingRule::factory()->create(['source_a_id' => $sourceA->id, 'source_b_id' => $sourceB->id]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->matched)->toBe(1);
    expect($summary->conflicts)->toBe(0);
    expect($summary->noSignal)->toBe(0);

    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Matched);
    expect((float) $result->confidence_score)->toBe(100.0);
    expect($result->matched_by)->toBeNull();
    expect($result->matchingDetails)->toHaveCount(2);

    expect($ntA->fresh()->matching_status->value)->toBe('matched');
    expect($ntB->fresh()->matching_status->value)->toBe('matched');
});

test('same date but different amount creates a Conflict result and an AmountMismatch exception', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    $ntA = makeNormalizedTx($sourceA, '077025', 84000, '2026-01-08');
    $ntB = makeNormalizedTx($sourceB, '077025', 98000, '2026-01-08');

    $rule = \App\Models\MatchingRule::factory()->create(['source_a_id' => $sourceA->id, 'source_b_id' => $sourceB->id]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->matched)->toBe(0);
    expect($summary->conflicts)->toBe(1);

    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Conflict);
    expect($result->confidence_score)->toBeNull();

    $exception = ExceptionRecord::query()->sole();
    expect($exception->type)->toBe(ExceptionType::AmountMismatch);
    expect($exception->matching_result_id)->toBe($result->id);
    expect($exception->normalized_transaction_id)->toBeNull();

    expect($ntA->fresh()->matching_status->value)->toBe('conflict');
    expect($ntB->fresh()->matching_status->value)->toBe('conflict');
});

test('same amount but different date creates a Conflict result and a DateMismatch exception', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '030046', 13000, '2026-01-25');
    makeNormalizedTx($sourceB, '030046', 13000, '2026-01-01');

    $rule = \App\Models\MatchingRule::factory()->create(['source_a_id' => $sourceA->id, 'source_b_id' => $sourceB->id]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->conflicts)->toBe(1);
    expect(ExceptionRecord::query()->sole()->type)->toBe(ExceptionType::DateMismatch);
});

test('neither amount nor date matching creates no result and no exception, both sides stay unmatched', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    $ntA = makeNormalizedTx($sourceA, '363528', 27000, '2026-01-07');
    $ntB = makeNormalizedTx($sourceB, '363528', 275000, '2026-01-01');

    $rule = \App\Models\MatchingRule::factory()->create(['source_a_id' => $sourceA->id, 'source_b_id' => $sourceB->id]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->matched)->toBe(0);
    expect($summary->conflicts)->toBe(0);
    expect($summary->noSignal)->toBe(1);

    expect(MatchingResult::query()->count())->toBe(0);
    expect(ExceptionRecord::query()->count())->toBe(0);
    expect($ntA->fresh()->matching_status->value)->toBe('unmatched');
    expect($ntB->fresh()->matching_status->value)->toBe('unmatched');
});

test('an N:1 group (2 rows side A, 1 row side B) with matching sums produces one result with 3 details', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '999111', 50000, '2026-01-10');
    makeNormalizedTx($sourceA, '999111', 30000, '2026-01-10');
    makeNormalizedTx($sourceB, '999111', 80000, '2026-01-10');

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $sourceA->id,
        'source_b_id' => $sourceB->id,
        'cardinality' => 'N:M',
    ]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->matched)->toBe(1);
    $result = MatchingResult::query()->sole();
    expect($result->matchingDetails)->toHaveCount(3);
    expect($result->notes)->toBeNull(); // rule is N:M, observed 2:1 doesn't need a mismatch note under N:M
});

test('a cardinality mismatch is recorded as a note but the match still succeeds', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '555222', 50000, '2026-01-10');
    makeNormalizedTx($sourceA, '555222', 30000, '2026-01-10');
    makeNormalizedTx($sourceB, '555222', 80000, '2026-01-10');

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $sourceA->id,
        'source_b_id' => $sourceB->id,
        'cardinality' => '1:1',
    ]);

    $this->matcher->match($rule, 'batch-1');

    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Matched);
    expect($result->notes)->not->toBeNull();
    expect($result->notes)->toContain('2:1');
});

test('excluded_status_raw filters a row out of the candidate pool even with a matching reference', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '345547', 4750, '2026-01-31', statusRaw: 'Commission');
    makeNormalizedTx($sourceB, '345547', 4750, '2026-01-31');

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $sourceA->id,
        'source_b_id' => $sourceB->id,
        'criteria' => [
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => ['Commission'], 'b' => []],
        ],
    ]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->referencesConsidered)->toBe(0);
    expect(MatchingResult::query()->count())->toBe(0);
});

test('rerunning the same rule twice is idempotent and creates nothing new', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '843500', 1773000, '2026-01-15');
    makeNormalizedTx($sourceB, '843500', 1773000, '2026-01-15');

    $rule = \App\Models\MatchingRule::factory()->create(['source_a_id' => $sourceA->id, 'source_b_id' => $sourceB->id]);

    $this->matcher->match($rule, 'batch-1');
    $firstCount = MatchingResult::query()->count();

    $secondSummary = $this->matcher->match($rule, 'batch-2');

    expect(MatchingResult::query()->count())->toBe($firstCount);
    expect($secondSummary->referencesConsidered)->toBe(0);
});

test('a reference reused by two distinct transactions on different dates matches exactly via multiset equality', function () {
    // Real ALPHA<->BNA case: reference 077025 covers one transaction on
    // 2026-01-01 and another on 2026-01-08, each appearing once per source.
    // A naive sum+date-spread check sees a 7-day spread and misclassifies
    // this as a DateMismatch conflict even though every individual
    // transaction pairs perfectly.
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '077025', 98000, '2026-01-01');
    makeNormalizedTx($sourceA, '077025', 84000, '2026-01-08');
    makeNormalizedTx($sourceB, '077025', 84000, '2026-01-08');
    makeNormalizedTx($sourceB, '077025', 98000, '2026-01-01');

    $rule = \App\Models\MatchingRule::factory()->create(['source_a_id' => $sourceA->id, 'source_b_id' => $sourceB->id]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->matched)->toBe(1);
    expect($summary->conflicts)->toBe(0);

    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Matched);
    expect((float) $result->confidence_score)->toBe(100.0);
    expect(ExceptionRecord::query()->count())->toBe(0);
});

test('a genuine mismatch is still a conflict even when group sizes and sums coincidentally align', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '999888', 100000, '2026-01-01');
    makeNormalizedTx($sourceA, '999888', 50000, '2026-01-08');
    makeNormalizedTx($sourceB, '999888', 120000, '2026-01-01');
    makeNormalizedTx($sourceB, '999888', 30000, '2026-01-08');

    $rule = \App\Models\MatchingRule::factory()->create(['source_a_id' => $sourceA->id, 'source_b_id' => $sourceB->id]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->matched)->toBe(0);
    expect($summary->conflicts)->toBe(1);
    expect(MatchingResult::query()->sole()->status)->toBe(MatchingResultStatus::Conflict);
});

test('a tolerance-consumed partial match produces a Partial status with reduced confidence', function () {
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    makeNormalizedTx($sourceA, '777888', 100000, '2026-01-10');
    makeNormalizedTx($sourceB, '777888', 100500, '2026-01-10');

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $sourceA->id,
        'source_b_id' => $sourceB->id,
        'criteria' => [
            'tolerance_amount_millimes' => 1000,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
        ],
    ]);

    $summary = $this->matcher->match($rule, 'batch-1');

    expect($summary->matched)->toBe(1);
    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Partial);
    expect((float) $result->confidence_score)->toBe(85.0);
});

test('WEB-BNA matches via secondary_reference (recu_paie) against num_autorisation', function () {
    $web = Source::factory()->create();
    $bna = Source::factory()->create();

    // WEB recu_paie = 'b416779' → stripped b → '416779'; BNA num_autorisation = '416779'
    $ntWeb = makeNormalizedTx($web, '104725801', 16000, '2026-02-01');
    $ntWeb->transaction->raw_payload = ['secondary_reference' => '416779'];
    $ntWeb->transaction->save();

    $ntBna = makeNormalizedTx($bna, '416779', 16000, '2026-02-01');
    $ntBna->transaction->raw_payload = ['num_autorisation' => '416779'];
    $ntBna->transaction->save();

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $web->id,
        'source_b_id' => $bna->id,
        'criteria' => [
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
            'primary_key' => ['a' => 'secondary_reference', 'b' => 'num_autorisation'],
            'verify_fields' => ['amount', 'date'],
        ],
    ]);

    $summary = $this->matcher->match($rule, 'web-bna-1');

    expect($summary->matched)->toBe(1);
    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Matched);
    expect($result->matchingDetails)->toHaveCount(2);
});

test('ALPHA-WEB matches via reference with num_autorisation-secondary_reference verification', function () {
    $alpha = Source::factory()->create();
    $web = Source::factory()->create();

    // ALPHA: reference='104725801', num_autorisation='416779'
    // WEB:   reference='104725801', secondary_reference='416779'
    $ntAlpha = makeNormalizedTx($alpha, '104725801', 16000, '2026-02-01');
    $ntAlpha->transaction->raw_payload = ['num_autorisation' => '416779'];
    $ntAlpha->transaction->save();

    $ntWeb = makeNormalizedTx($web, '104725801', 16000, '2026-02-01');
    $ntWeb->transaction->raw_payload = ['secondary_reference' => '416779'];
    $ntWeb->transaction->save();

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $alpha->id,
        'source_b_id' => $web->id,
        'criteria' => [
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
            'primary_key' => ['a' => 'reference', 'b' => 'reference'],
            'verify_fields' => [
                ['a' => 'num_autorisation', 'b' => 'secondary_reference'],
                'amount',
                'date',
            ],
        ],
    ]);

    $summary = $this->matcher->match($rule, 'alpha-web-1');

    expect($summary->matched)->toBe(1);
    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Matched);
    expect($result->matchingDetails)->toHaveCount(2);
});

test('ALPHA-WEB with mismatched num_autorisation produces no_signal (no match, no exception)', function () {
    $alpha = Source::factory()->create();
    $web = Source::factory()->create();

    // Same reference but different num_autorisation/secondary_reference
    $ntAlpha = makeNormalizedTx($alpha, '104725801', 16000, '2026-02-01');
    $ntAlpha->transaction->raw_payload = ['num_autorisation' => '111111'];
    $ntAlpha->transaction->save();

    $ntWeb = makeNormalizedTx($web, '104725801', 16000, '2026-02-01');
    $ntWeb->transaction->raw_payload = ['secondary_reference' => '416779'];
    $ntWeb->transaction->save();

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $alpha->id,
        'source_b_id' => $web->id,
        'criteria' => [
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
            'primary_key' => ['a' => 'reference', 'b' => 'reference'],
            'verify_fields' => [
                ['a' => 'num_autorisation', 'b' => 'secondary_reference'],
                'amount',
                'date',
            ],
        ],
    ]);

    $summary = $this->matcher->match($rule, 'alpha-web-2');

    expect($summary->matched)->toBe(0);
    expect($summary->noSignal)->toBe(1);
    expect(MatchingResult::query()->count())->toBe(0);
    expect(ExceptionRecord::query()->count())->toBe(0);
    expect($ntAlpha->fresh()->matching_status->value)->toBe('unmatched');
    expect($ntWeb->fresh()->matching_status->value)->toBe('unmatched');
});

test('SMT composite date|amount key matches across sources', function () {
    $smt = Source::factory()->create();
    $bna = Source::factory()->create();

    // SMT has no reference — only date + amount. The composite key
    // 'date|amount' should group both sides.
    makeNormalizedTx($smt, '2026-02-01|16000', 16000, '2026-02-01');
    makeNormalizedTx($bna, '416779', 16000, '2026-02-01');

    $rule = \App\Models\MatchingRule::factory()->create([
        'source_a_id' => $smt->id,
        'source_b_id' => $bna->id,
        'criteria' => [
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
            'primary_key' => ['a' => 'date|amount', 'b' => 'date|amount'],
            'verify_fields' => [],
        ],
    ]);

    $summary = $this->matcher->match($rule, 'smt-bna-1');

    expect($summary->matched)->toBe(1);
    $result = MatchingResult::query()->sole();
    expect($result->status)->toBe(MatchingResultStatus::Matched);
    expect($result->matchingDetails)->toHaveCount(2);
});
