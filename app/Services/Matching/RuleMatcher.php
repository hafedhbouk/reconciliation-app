<?php

namespace App\Services\Matching;

use App\DataTransferObjects\MatchingRunSummary;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\MatchingCardinality;
use App\Enums\MatchingResultStatus;
use App\Enums\MatchingStatus;
use App\Models\ExceptionRecord;
use App\Models\MatchingDetail;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Groups unmatched NormalizedTransactions across a rule's two sources by
 * normalized_reference and applies a 3-way amount/date tolerance branch —
 * NOT a simple match/no-match binary. Verified against real data: a 6-digit
 * reference space collides at real transaction volume (~3,482 of ~75,000
 * cross-referenced ALPHA/BNA pairs share a reference but nothing else), so
 * reference-only matching is not trustworthy. The branch:
 *
 *   1. Amount AND date both within tolerance  -> genuine match.
 *   2. Exactly one of the two within tolerance -> a real conflict signal
 *      (right reference, something's off) -> ExceptionRecord.
 *   3. Neither within tolerance -> almost certainly an unrelated reference
 *      collision -> do nothing (no link, no exception, both sides stay
 *      unmatched). Treating this as a conflict would flood the exceptions
 *      table with false positives for zero operational value.
 *
 * Before that 3-way branch, evaluateGroup() first checks for an EXACT
 * multiset match (same bag of (amount, date) pairs on both sides, regardless
 * of order). This is not a redundant fast path -- verified against real
 * ALPHA/BNA data: a reference can be reused by two or more genuinely
 * distinct transactions on different dates (e.g. reference 077025 covering
 * one transaction on 2026-01-01 and another on 2026-01-08, each present once
 * per source). The sum+date-spread branch alone sums the whole group's
 * amount (correctly, since totals agree) but measures date spread across the
 * WHOLE group, which is nonzero here even though every individual
 * transaction pairs perfectly -- so it misclassified 3,487 of 3,490 real
 * ALPHA<->BNA "conflicts" as DateMismatch when they were exact matches.
 * Multiset equality catches these directly; the sum+spread branch remains
 * the fallback for genuine tolerance-consumed Partial matches and real
 * conflicts.
 */
class RuleMatcher
{
    public function __construct(private ConfidenceScorer $scorer)
    {
    }

    public function match(MatchingRule $rule, string $batchReference): MatchingRunSummary
    {
        $criteria = $rule->criteria ?? [];
        $toleranceAmount = (int) ($criteria['tolerance_amount_millimes'] ?? 0);
        $toleranceDays = (int) ($criteria['tolerance_days'] ?? 0);
        $excludedA = $criteria['excluded_status_raw']['a'] ?? [];
        $excludedB = $criteria['excluded_status_raw']['b'] ?? [];

        $candidatesA = $this->loadCandidates($rule->source_a_id, $excludedA);
        $candidatesB = $this->loadCandidates($rule->source_b_id, $excludedB);

        $matched = 0;
        $conflicts = 0;
        $noSignal = 0;
        $skipped = 0;

        $references = $candidatesA->keys()->intersect($candidatesB->keys());

        foreach ($references as $reference) {
            $outcome = $this->evaluateGroup(
                $candidatesA->get($reference),
                $candidatesB->get($reference),
                $rule,
                $toleranceAmount,
                $toleranceDays,
                $batchReference,
            );

            match ($outcome) {
                'matched' => $matched++,
                'conflict' => $conflicts++,
                'skipped' => $skipped++,
                default => $noSignal++,
            };
        }

        return new MatchingRunSummary(
            referencesConsidered: $references->count(),
            matched: $matched,
            conflicts: $conflicts,
            noSignal: $noSignal,
            skipped: $skipped,
        );
    }

    /** @return Collection<string,Collection<int,NormalizedTransaction>> keyed by normalized_reference */
    private function loadCandidates(int $sourceId, array $excludedStatusRaw): Collection
    {
        return NormalizedTransaction::query()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->where('transactions.source_id', $sourceId)
            ->where('normalized_transactions.matching_status', MatchingStatus::Unmatched->value)
            ->when($excludedStatusRaw !== [], fn ($query) => $query->where(function ($inner) use ($excludedStatusRaw) {
                $inner->whereNull('transactions.raw_payload->status_raw')
                    ->orWhereNotIn('transactions.raw_payload->status_raw', $excludedStatusRaw);
            }))
            ->select('normalized_transactions.*')
            ->get()
            ->groupBy('normalized_reference');
    }

    /**
     * @param Collection<int,NormalizedTransaction> $groupA
     * @param Collection<int,NormalizedTransaction> $groupB
     * @return string 'matched'|'conflict'|'no_signal'|'skipped'
     */
    private function evaluateGroup(
        Collection $groupA,
        Collection $groupB,
        MatchingRule $rule,
        int $toleranceAmount,
        int $toleranceDays,
        string $batchReference,
    ): string {
        [$amountOk, $amountExact, $dateOk, $dateExact] = $this->computeToleranceSignals(
            $groupA, $groupB, $toleranceAmount, $toleranceDays,
        );

        return DB::transaction(function () use ($groupA, $groupB, $rule, $amountOk, $dateOk, $amountExact, $dateExact, $batchReference) {
            $allIds = $groupA->pluck('id')->merge($groupB->pluck('id'));

            // Defense-in-depth: the candidate query already scopes to matching_status
            // = unmatched, so this should always hold; re-check immediately before
            // writing in case a concurrent job already claimed part of this group.
            $stillUnmatched = NormalizedTransaction::query()
                ->whereIn('id', $allIds)
                ->where('matching_status', MatchingStatus::Unmatched->value)
                ->count();

            if ($stillUnmatched !== $allIds->count()) {
                return 'skipped';
            }

            if ($amountOk && $dateOk) {
                $status = ($amountExact && $dateExact) ? MatchingResultStatus::Matched : MatchingResultStatus::Partial;
                $confidence = $this->scorer->score($amountExact, $dateExact);
                $this->persistOutcome($groupA, $groupB, $rule, $status, $confidence, null, $batchReference);

                return 'matched';
            }

            if ($amountOk xor $dateOk) {
                $exceptionType = $dateOk ? ExceptionType::AmountMismatch : ExceptionType::DateMismatch;
                $this->persistOutcome($groupA, $groupB, $rule, MatchingResultStatus::Conflict, null, $exceptionType, $batchReference);

                return 'conflict';
            }

            return 'no_signal';
        });
    }

    /**
     * @param Collection<int,NormalizedTransaction> $groupA
     * @param Collection<int,NormalizedTransaction> $groupB
     * @return array{0:bool,1:bool,2:bool,3:bool} [amountOk, amountExact, dateOk, dateExact]
     */
    private function computeToleranceSignals(Collection $groupA, Collection $groupB, int $toleranceAmount, int $toleranceDays): array
    {
        $exactMultisetMatch = $this->multisetsMatchExactly($groupA, $groupB);

        $sumA = (int) $groupA->sum('normalized_amount_millimes');
        $sumB = (int) $groupB->sum('normalized_amount_millimes');
        $amountOk = $exactMultisetMatch || abs($sumA - $sumB) <= $toleranceAmount;
        $amountExact = $exactMultisetMatch || $sumA === $sumB;

        $allDates = $groupA->pluck('normalized_date')->merge($groupB->pluck('normalized_date'));
        $minDate = $allDates->min();
        $maxDate = $allDates->max();
        // Carbon 3 (Laravel 12) returns a float from diffInDays() by default, so an
        // exact same-day match yields 0.0, not 0 -- an int-strict comparison below
        // would silently downgrade every true exact match to Partial.
        $dateSpreadDays = (int) round(abs($minDate->diffInDays($maxDate)));
        $dateOk = $exactMultisetMatch || $dateSpreadDays <= $toleranceDays;
        $dateExact = $exactMultisetMatch || $dateSpreadDays === 0;

        return [$amountOk, $amountExact, $dateOk, $dateExact];
    }

    /**
     * True when groupA and groupB contain the exact same bag of
     * (amount, date) pairs, regardless of order. Catches a reference reused
     * by two or more genuinely distinct transactions on different dates --
     * see class docblock for the real ALPHA/BNA case this was built for.
     *
     * @param Collection<int,NormalizedTransaction> $groupA
     * @param Collection<int,NormalizedTransaction> $groupB
     */
    private function multisetsMatchExactly(Collection $groupA, Collection $groupB): bool
    {
        if ($groupA->count() !== $groupB->count()) {
            return false;
        }

        $keyOf = fn (NormalizedTransaction $nt) => $nt->normalized_amount_millimes.'|'.$nt->normalized_date->format('Y-m-d');

        $bagA = $groupA->map($keyOf)->sort()->values();
        $bagB = $groupB->map($keyOf)->sort()->values();

        return $bagA->all() === $bagB->all();
    }

    /**
     * @param Collection<int,NormalizedTransaction> $groupA
     * @param Collection<int,NormalizedTransaction> $groupB
     */
    private function persistOutcome(
        Collection $groupA,
        Collection $groupB,
        MatchingRule $rule,
        MatchingResultStatus $status,
        ?float $confidence,
        ?ExceptionType $exceptionType,
        string $batchReference,
    ): void {
        $result = MatchingResult::create([
            'matching_rule_id' => $rule->id,
            'batch_reference' => $batchReference,
            'status' => $status,
            'confidence_score' => $confidence,
            'matched_by' => null,
            'matched_at' => now(),
            'notes' => $this->cardinalityNote($rule, $groupA->count(), $groupB->count()),
        ]);

        $now = now();
        $details = $groupA->map(fn ($nt) => [
            'matching_result_id' => $result->id,
            'normalized_transaction_id' => $nt->id,
            'side' => 'a',
            'created_at' => $now,
            'updated_at' => $now,
        ])->merge($groupB->map(fn ($nt) => [
            'matching_result_id' => $result->id,
            'normalized_transaction_id' => $nt->id,
            'side' => 'b',
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        MatchingDetail::insert($details->all());

        $newMatchingStatus = $status === MatchingResultStatus::Conflict
            ? MatchingStatus::Conflict
            : MatchingStatus::Matched;

        NormalizedTransaction::query()
            ->whereIn('id', $groupA->pluck('id')->merge($groupB->pluck('id')))
            ->update(['matching_status' => $newMatchingStatus->value]);

        if ($exceptionType !== null) {
            ExceptionRecord::create([
                'normalized_transaction_id' => null,
                'matching_result_id' => $result->id,
                'type' => $exceptionType,
                'status' => ExceptionStatus::Open,
            ]);
        }
    }

    private function cardinalityNote(MatchingRule $rule, int $countA, int $countB): ?string
    {
        // N:M is the deliberate "no constraint" configuration (see Phase 3 design
        // decision 4 — seeded default for all 6 rules precisely because the true
        // shape isn't reliably 1:1) so it never produces a mismatch note; only a
        // narrower configured cardinality (1:1, 1:N, N:1) can be diverged from.
        if ($rule->cardinality === MatchingCardinality::ManyToMany) {
            return null;
        }

        $observed = match (true) {
            $countA === 1 && $countB === 1 => MatchingCardinality::OneToOne,
            $countA === 1 && $countB > 1 => MatchingCardinality::OneToMany,
            $countA > 1 && $countB === 1 => MatchingCardinality::ManyToOne,
            default => MatchingCardinality::ManyToMany,
        };

        if ($observed === $rule->cardinality) {
            return null;
        }

        return "Cardinalité effective : {$countA}:{$countB} ({$observed->label()}) — règle configurée en {$rule->cardinality->label()}.";
    }
}
