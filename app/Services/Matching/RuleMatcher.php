<?php

namespace App\Services\Matching;

/**
 * Moteur de rapprochement : applique une règle pour comparer deux sources.
 *
 * Le matching fonctionne en 3 étapes :
 * 1. Groupement par clé primaire configurable (référence, date|montant, etc.)
 * 2. Vérification des champs secondaires (verify_fields)
 * 3. Branche de tolérance 3 voies : match exact, conflit (un seul critère
 *    respecté) ou sans signal (aucun critère respecté).
 *
 * Un chemin rapide multiset vérifie l'égalité parfaite des paires
 * (montant, date) pour éviter les faux conflits quand une référence est
 * réutilisée sur plusieurs dates distinctes.
 */
use App\DataTransferObjects\MatchingRunSummary;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\MatchingCardinality;
use App\Enums\MatchingResultStatus;
use App\Enums\MatchingStatus;
use App\Models\ExceptionRecord;
use App\Models\Import;
use App\Models\MatchingDetail;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Models\UnmatchedSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Groups unmatched NormalizedTransactions across a rule's two sources by a
 * configurable primary key (from criteria.primary_key) and applies a 3-way
 * amount/date tolerance branch — NOT a simple match/no-match binary.
 *
 * The primary key can be:
 *   - a single field name ('reference', 'num_autorisation',
 *     'secondary_reference') — resolved from normalized_reference or from
 *     the transaction's raw_payload (transformed fields)
 *   - 'date|amount' — a composite key for SMT (date + amount only)
 *
 * After grouping by the primary key, criteria.verify_fields are checked to
 * confirm the match (e.g. ALPHA-WEB groups by reference, then verifies
 * num_autorisation-secondary_reference, amount, date).
 *
 * The 3-way branch:
 *   1. Amount AND date both within tolerance  -> genuine match.
 *   2. Exactly one of the two within tolerance -> a real conflict signal
 *      (right primary key, something's off) -> ExceptionRecord.
 *   3. Neither within tolerance -> almost certainly an unrelated key
 *      collision -> do nothing (no link, no exception, both sides stay
 *      unmatched).
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
    public function __construct(private ConfidenceScorer $scorer) {}

    public function match(MatchingRule $rule, string $batchReference, ?int $importIdA = null, ?int $importIdB = null): MatchingRunSummary
    {
        $criteria = $rule->criteria ?? [];
        if ($criteria['file_comparison'] ?? false) {
            if ($importIdA === null || $importIdB === null || $importIdA === $importIdB) {
                throw new \InvalidArgumentException('Deux fichiers distincts sont nécessaires pour cette comparaison.');
            }

            return $this->matchFiles($rule, $batchReference, $importIdA, $importIdB);
        }
        $toleranceAmount = (int) ($criteria['tolerance_amount_millimes'] ?? 0);
        $toleranceDays = (int) ($criteria['tolerance_days'] ?? 0);
        $excludedA = $criteria['excluded_status_raw']['a'] ?? [];
        $excludedB = $criteria['excluded_status_raw']['b'] ?? [];
        $primaryKeyA = $criteria['primary_key']['a'] ?? 'reference';
        $primaryKeyB = $criteria['primary_key']['b'] ?? 'reference';
        $verifyFields = $criteria['verify_fields'] ?? [];

        $candidatesA = $this->loadCandidates($rule->source_a_id, $excludedA, $primaryKeyA, $importIdA);
        $candidatesB = $this->loadCandidates($rule->source_b_id, $excludedB, $primaryKeyB, $importIdB);

        $matched = 0;
        $conflicts = 0;
        $noSignal = 0;
        $skipped = 0;

        $keys = $candidatesA->keys()->intersect($candidatesB->keys());

        // Traiter uniquement les clés présentes dans les deux sources.
        foreach ($keys as $key) {
            $outcome = $this->evaluateGroup(
                $candidatesA->get($key),
                $candidatesB->get($key),
                $rule,
                $toleranceAmount,
                $toleranceDays,
                $batchReference,
                $verifyFields,
            );

            match ($outcome) {
                'matched' => $matched++,
                'conflict' => $conflicts++,
                'skipped' => $skipped++,
                default => $noSignal++,
            };
        }

        $summary = new MatchingRunSummary(
            referencesConsidered: $keys->count(),
            matched: $matched,
            conflicts: $conflicts,
            noSignal: $noSignal,
            skipped: $skipped,
        );

        return $summary;
    }

    /** Compare complete rows, consuming each occurrence once before examining conflicts. */
    private function matchFiles(MatchingRule $rule, string $batchReference, int $importIdA, int $importIdB, bool $persistResults = true): MatchingRunSummary
    {
        $criteria = $rule->criteria;
        $primaryA = $criteria['primary_key']['a'];
        $primaryB = $criteria['primary_key']['b'];
        $verifyFields = $criteria['verify_fields'] ?? [];
        $a = $this->loadCandidates($rule->source_a_id, [], $primaryA, $importIdA, true);
        $b = $this->loadCandidates($rule->source_b_id, [], $primaryB, $importIdB, true);

        return DB::transaction(function () use ($a, $b, $rule, $batchReference, $importIdA, $importIdB, $primaryA, $primaryB, $verifyFields, $persistResults) {
            $matched = 0;
            $conflicts = 0;
            $unmatchedA = collect();
            $unmatchedB = collect();
            $keys = $a->keys()->merge($b->keys())->unique();

            foreach ($keys as $key) {
                $groupA = $a->get($key, collect());
                $groupB = $b->get($key, collect());
                $fullA = $groupA->groupBy(fn ($nt) => $this->fileSignature($nt, 'a', $verifyFields));
                $fullB = $groupB->groupBy(fn ($nt) => $this->fileSignature($nt, 'b', $verifyFields));
                $remainingA = collect();
                $remainingB = collect();

                foreach ($fullA->keys()->merge($fullB->keys())->unique() as $signature) {
                    $rowsA = $fullA->get($signature, collect());
                    $rowsB = $fullB->get($signature, collect());
                    $count = min($rowsA->count(), $rowsB->count());
                    if ($count > 0) {
                        if ($persistResults) {
                            $this->persistOutcome($rowsA->take($count), $rowsB->take($count), $rule,
                                MatchingResultStatus::Matched, 100.0, null, $batchReference);
                        }
                        $matched++;
                    }
                    $remainingA = $remainingA->merge($rowsA->slice($count)->values());
                    $remainingB = $remainingB->merge($rowsB->slice($count)->values());
                }

                // Same identifier, but at least one compared field differs.
                // Do not aggregate amounts: 10 + 20 is not the same row as 30.
                if ($remainingA->isNotEmpty() && $remainingB->isNotEmpty()) {
                    $amountsEqual = $remainingA->pluck('normalized_amount_millimes')->sort()->values()->all()
                        === $remainingB->pluck('normalized_amount_millimes')->sort()->values()->all();
                    $dates = fn ($rows) => $rows->map(fn ($nt) => $nt->normalized_date?->format('Y-m-d'))->sort()->values()->all();
                    $datesEqual = $dates($remainingA) === $dates($remainingB);
                    $type = match (true) {
                        ! $this->verifyFieldsMatch($remainingA, $remainingB, $verifyFields) => ExceptionType::Conflict,
                        $datesEqual && ! $amountsEqual => ExceptionType::AmountMismatch,
                        $amountsEqual && ! $datesEqual => ExceptionType::DateMismatch,
                        default => ExceptionType::Conflict,
                    };
                    if ($persistResults) {
                        $this->persistOutcome($remainingA, $remainingB, $rule, MatchingResultStatus::Conflict, null, $type, $batchReference);
                    }
                    $conflicts++;
                } else {
                    $unmatchedA = $unmatchedA->merge($remainingA);
                    $unmatchedB = $unmatchedB->merge($remainingB);
                }
            }

            // This snapshot is relative to the two files, regardless of each
            // row's status in earlier comparisons with other files.
            UnmatchedSnapshot::updateOrCreate(
                ['import_a_id' => $importIdA, 'import_b_id' => $importIdB],
                [
                    'status' => 'completed',
                    'result_a' => $unmatchedA->map(fn ($nt) => $this->fileSnapshotRow($nt, $primaryA))->values()->all(),
                    'result_b' => $unmatchedB->map(fn ($nt) => $this->fileSnapshotRow($nt, $primaryB))->values()->all(),
                    'error' => null,
                    'started_at' => now(),
                    'completed_at' => now(),
                ],
            );

            return new MatchingRunSummary($keys->count(), $matched, $conflicts, 0, 0, $unmatchedA->count(), $unmatchedB->count());
        });
    }

    /** Refresh only the differences snapshot; do not create matches or change transaction statuses. */
    public function refreshFileDifferences(Import $a, Import $b): MatchingRunSummary
    {
        $rule = new MatchingRule([
            'source_a_id' => $a->source_id,
            'source_b_id' => $b->source_id,
            'criteria' => app(FileComparisonRules::class)->criteria($a->source, $b->source),
        ]);

        return $this->matchFiles($rule, '', $a->id, $b->id, false);
    }

    private function fileSignature(NormalizedTransaction $nt, string $side, array $verifyFields): string
    {
        $values = [$nt->normalized_date?->format('Y-m-d'), $nt->normalized_amount_millimes];
        foreach ($verifyFields as $field) {
            if (is_array($field)) {
                $values[] = $this->fieldValue($nt, $field[$side]);
            }
        }
        if (collect($values)->contains(fn ($value) => $value === null || trim((string) $value) === '')) {
            return 'missing:'.$nt->id;
        }

        return json_encode($values, JSON_THROW_ON_ERROR);
    }

    private function fileSnapshotRow(NormalizedTransaction $nt, string|array $primaryKey): array
    {
        return [
            'id' => $nt->id,
            'source' => $nt->transaction->source->code,
            'reference' => $nt->normalized_reference,
            'amount_millimes' => $nt->normalized_amount_millimes,
            'date' => $nt->normalized_date?->format('d/m/Y'),
            'primary_key_value' => $this->primaryKeyValue($nt, $primaryKey),
        ];
    }

    /**
     * @return Collection<string,Collection<int,NormalizedTransaction>> keyed by the configured primary key
     */
    private function loadCandidates(int $sourceId, array $excludedStatusRaw, string|array $primaryKey, ?int $importId = null, bool $allStatuses = false): Collection
    {
        $rows = NormalizedTransaction::query()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->where('transactions.source_id', $sourceId)
            ->when(! $allStatuses, fn ($query) => $query->where('normalized_transactions.matching_status', MatchingStatus::Unmatched->value))
            ->when($allStatuses, fn ($query) => $query->with('transaction.source'))
            ->when($importId !== null, fn ($query) => $query->where('transactions.import_id', $importId))
            ->when($excludedStatusRaw !== [], fn ($query) => $query->where(function ($inner) use ($excludedStatusRaw) {
                $inner->whereNull('transactions.raw_payload->status_raw')
                    ->orWhereNotIn('transactions.raw_payload->status_raw', $excludedStatusRaw);
            }))
            ->select('normalized_transactions.*', 'transactions.raw_payload')
            ->get();

        $grouped = $rows->groupBy(function (NormalizedTransaction $nt) use ($primaryKey, $allStatuses) {
            if ($allStatuses) {
                $fields = $primaryKey === 'date|amount' ? ['date', 'amount'] : (array) $primaryKey;
                foreach ($fields as $field) {
                    $value = $field === 'reference' ? $nt->transaction->external_reference : $this->fieldValue($nt, $field);
                    if ($value === null || trim((string) $value) === '') {
                        return 'missing:'.$nt->id;
                    }
                }

                // Keep identifiers textual: Collection::unique() otherwise
                // treats "000123456" and the numeric array key 123456 as equal,
                // discarding one side's group from the file comparison.
                return 'key:'.$this->primaryKeyValue($nt, $primaryKey);
            }

            return $this->primaryKeyValue($nt, $primaryKey);
        });

        return $grouped;
    }

    /**
     * Resolve the configured primary key value for a normalized transaction.
     * Supports simple keys ('reference', 'num_autorisation', 'date|amount')
     * and composite keys (array of field names joined by '|').
     */
    private function primaryKeyValue(NormalizedTransaction $nt, string|array $primaryKey): string
    {
        if ($primaryKey === 'date|amount') {
            return $nt->normalized_date?->format('Y-m-d').'|'.$nt->normalized_amount_millimes;
        }

        if ($primaryKey === 'reference') {
            return (string) $nt->normalized_reference;
        }

        if (is_array($primaryKey)) {
            $payload = $this->rawPayload($nt);
            $parts = [];

            foreach ($primaryKey as $field) {
                if ($field === 'date|amount') {
                    $parts[] = $nt->normalized_date->format('Y-m-d').'|'.$nt->normalized_amount_millimes;
                } elseif ($field === 'reference') {
                    $parts[] = (string) $nt->normalized_reference;
                } else {
                    $parts[] = (string) ($payload[$field] ?? '');
                }
            }

            return implode('|', $parts);
        }

        $payload = $this->rawPayload($nt);

        return (string) ($payload[$primaryKey] ?? '');
    }

    private function rawPayload(NormalizedTransaction $nt): array
    {
        $payload = $nt->getAttribute('raw_payload');

        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?? [];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  Collection<int,NormalizedTransaction>  $groupA
     * @param  Collection<int,NormalizedTransaction>  $groupB
     * @return string 'matched'|'conflict'|'no_signal'|'skipped'
     */
    private function evaluateGroup(
        Collection $groupA,
        Collection $groupB,
        MatchingRule $rule,
        int $toleranceAmount,
        int $toleranceDays,
        string $batchReference,
        array $verifyFields = [],
    ): string {
        // If verify_fields are configured, they must ALL match before we
        // even consider the amount/date tolerance branch. A mismatch here
        // means the primary key collided but the secondary fields don't
        // line up — treat as no_signal (no link, no exception).
        // Si des champs secondaires sont configurés, ils doivent tous
        // correspondre avant même d'appliquer la tolérance montant/date.
        // Un écart ici signifie une collision de clé primaire sans lien réel.
        if ($verifyFields !== [] && ! $this->verifyFieldsMatch($groupA, $groupB, $verifyFields)) {
            return 'no_signal';
        }

        [$amountOk, $amountExact, $dateOk, $dateExact] = $this->computeToleranceSignals(
            $groupA, $groupB, $toleranceAmount, $toleranceDays,
        );

        return DB::transaction(function () use ($groupA, $groupB, $rule, $amountOk, $dateOk, $amountExact, $dateExact, $batchReference) {
            $allIds = $groupA->pluck('id')->merge($groupB->pluck('id'));

            // Vérification défensive : un job concurrent peut avoir déjà
            // traité une partie de ce groupe pendant l'exécution.
            // MySQL has a 65 535 placeholder limit per prepared statement, so
            // large reference groups must be checked in chunks.
            $stillUnmatched = 0;
            foreach ($allIds->chunk(1000) as $chunk) {
                $stillUnmatched += NormalizedTransaction::query()
                    ->whereIn('id', $chunk)
                    ->where('matching_status', MatchingStatus::Unmatched->value)
                    ->count();
            }

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
     * Verify that all configured secondary fields match between the two
     * groups. Each entry in $verifyFields is either:
     *   - a string field name ('amount', 'date') — must match on both sides
     *   - an array ['a' => fieldA, 'b' => fieldB] — fieldA on side A must
     *     equal fieldB on side B (e.g. num_autorisation ↔ secondary_reference)
     *
     * @param  Collection<int,NormalizedTransaction>  $groupA
     * @param  Collection<int,NormalizedTransaction>  $groupB
     * @param  array<int,mixed>  $verifyFields
     */
    private function verifyFieldsMatch(Collection $groupA, Collection $groupB, array $verifyFields): bool
    {
        foreach ($verifyFields as $field) {
            // 'amount' and 'date' are NOT gating here — they are handled by
            // the 3-way tolerance branch (match/conflict/no_signal) in
            // evaluateGroup(). Treating them as hard gates would silently
            // suppress genuine AmountMismatch/DateMismatch conflicts.
            if (is_string($field) && in_array($field, ['amount', 'date'], true)) {
                continue;
            }

            if (is_array($field)) {
                $fieldA = $field['a'] ?? null;
                $fieldB = $field['b'] ?? null;

                if ($fieldA === null || $fieldB === null) {
                    continue;
                }

                // Also skip cross-field checks that resolve to amount/date.
                if (in_array($fieldA, ['amount', 'date'], true) || in_array($fieldB, ['amount', 'date'], true)) {
                    continue;
                }

                $valuesA = $groupA->map(fn (NormalizedTransaction $nt) => $this->fieldValue($nt, $fieldA))->unique()->values();
                $valuesB = $groupB->map(fn (NormalizedTransaction $nt) => $this->fieldValue($nt, $fieldB))->unique()->values();

                if ($valuesA->count() !== 1 || $valuesB->count() !== 1 || $valuesA->first() !== $valuesB->first()) {
                    return false;
                }
            } else {
                $valuesA = $groupA->map(fn (NormalizedTransaction $nt) => $this->fieldValue($nt, $field))->unique()->values();
                $valuesB = $groupB->map(fn (NormalizedTransaction $nt) => $this->fieldValue($nt, $field))->unique()->values();

                if ($valuesA->count() !== 1 || $valuesB->count() !== 1 || $valuesA->first() !== $valuesB->first()) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Resolve a field value from a normalized transaction. 'amount' and
     * 'date' map to normalized columns; everything else is read from the
     * transaction's raw_payload (transformed fields).
     */
    private function fieldValue(NormalizedTransaction $nt, string $field): mixed
    {
        return match ($field) {
            'amount' => $nt->normalized_amount_millimes,
            'date' => $nt->normalized_date?->format('Y-m-d'),
            default => $this->payloadField($nt, $field),
        };
    }

    private function payloadField(NormalizedTransaction $nt, string $field): mixed
    {
        $payload = $this->rawPayload($nt);

        return $payload[$field] ?? null;
    }

    /**
     * @param  Collection<int,NormalizedTransaction>  $groupA
     * @param  Collection<int,NormalizedTransaction>  $groupB
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
     * @param  Collection<int,NormalizedTransaction>  $groupA
     * @param  Collection<int,NormalizedTransaction>  $groupB
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
     * @param  Collection<int,NormalizedTransaction>  $groupA
     * @param  Collection<int,NormalizedTransaction>  $groupB
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

        $allIds = $groupA->pluck('id')->merge($groupB->pluck('id'));

        foreach ($allIds->chunk(1000) as $chunk) {
            NormalizedTransaction::query()
                ->whereIn('id', $chunk)
                ->update(['matching_status' => $newMatchingStatus->value]);
        }

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
