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
 *
 * ---------------------------------------------------------------------------
 * GUIDE DÉBUTANT : le principe du rapprochement en un exemple
 * ---------------------------------------------------------------------------
 * Règle ALPHA ↔ BNA, clé = N° autorisation, vérification = montant + date.
 *
 *   ALPHA : #1 aut=697464 989000 01/05    BNA : #7 aut=697464 989000 01/05
 *           #2 aut=111111 25000  02/05          #8 aut=111111 26000  02/05
 *           #3 aut=222222 40000  03/05
 *
 *   1. On regroupe chaque côté par clé : {697464: [#1]}, {697464: [#7]}…
 *   2. On ne garde que les clés présentes DES DEUX côtés (697464, 111111).
 *   3. Pour chaque clé, on compare montants et dates :
 *        697464 : montant OK, date OK      -> « matched » (rapproché)
 *        111111 : date OK, montant différent -> « conflict » + exception AmountMismatch
 *   4. #3 (222222) n'a pas de partenaire : il reste « unmatched ».
 *
 * Deux façons d'utiliser cette classe :
 *   - match() avec une règle globale : compare TOUTES les transactions non
 *     rapprochées des deux sources (méthode loadCandidates + evaluateGroup) ;
 *   - match() avec criteria.file_comparison = true : compare deux FICHIERS
 *     précis, ligne par ligne (méthodes matchFiles + fileGroups).
 */
use App\DataTransferObjects\MatchingRunSummary;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\MatchingCardinality;
use App\Enums\MatchingResultStatus;
use App\Enums\MatchingStatus;
use App\Models\ComparisonRun;
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

    /**
     * Exécute une règle de rapprochement et renvoie un résumé chiffré.
     *
     * @param  string  $batchReference  identifiant du lot, enregistré sur chaque résultat créé
     * @param  int|null  $importIdA  limiter le côté A à un fichier (facultatif)
     * @param  int|null  $importIdB  limiter le côté B à un fichier (facultatif)
     */
    public function match(MatchingRule $rule, string $batchReference, ?int $importIdA = null, ?int $importIdB = null): MatchingRunSummary
    {
        $criteria = $rule->criteria ?? [];
        // Cas « comparaison de deux fichiers » : algorithme dédié (voir matchFiles).
        if ($criteria['file_comparison'] ?? false) {
            if ($importIdA === null || $importIdB === null || $importIdA === $importIdB) {
                throw new \InvalidArgumentException('Deux fichiers distincts sont nécessaires pour cette comparaison.');
            }

            return $this->matchFiles($rule, $batchReference, $importIdA, $importIdB);
        }
        // Lecture des paramètres de la règle (voir l'exemple de criteria dans app/Models/MatchingRule.php).
        $toleranceAmount = (int) ($criteria['tolerance_amount_millimes'] ?? 0);
        $toleranceDays = (int) ($criteria['tolerance_days'] ?? 0);
        $excludedA = $criteria['excluded_status_raw']['a'] ?? [];
        $excludedB = $criteria['excluded_status_raw']['b'] ?? [];
        $excludedNonNumericA = $criteria['excluded_non_numeric']['a'] ?? [];
        $excludedNonNumericB = $criteria['excluded_non_numeric']['b'] ?? [];
        $primaryKeyA = $criteria['primary_key']['a'] ?? 'reference';
        $primaryKeyB = $criteria['primary_key']['b'] ?? 'reference';
        $verifyFields = $criteria['verify_fields'] ?? [];

        // Étape 1 : transactions non rapprochées de chaque côté, regroupées par clé.
        // Résultat : ['697464' => [transactions…], '111111' => [...]].
        $candidatesA = $this->loadCandidates($rule->source_a_id, $excludedA, $primaryKeyA, $importIdA, false, $excludedNonNumericA);
        $candidatesB = $this->loadCandidates($rule->source_b_id, $excludedB, $primaryKeyB, $importIdB, false, $excludedNonNumericB);

        $matched = 0;
        $conflicts = 0;
        $noSignal = 0;
        $skipped = 0;

        // Étape 2 : seules les clés présentes des deux côtés peuvent être rapprochées.
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

            // Étape 3 terminée pour cette clé : on compte le résultat.
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

    /**
     * Compare complete rows, consuming each occurrence once before examining conflicts.
     *
     * Comparaison de deux FICHIERS. Toutes les lignes sont prises en compte,
     * quel que soit leur statut, et chaque ligne finit dans exactement une
     * catégorie : rapprochée, en conflit, ou exclusive (présente d'un seul
     * côté). Un bilan vérifie à la fin qu'aucune ligne ni aucun millime n'a
     * été perdu.
     *
     * Algorithme (« fusion de deux listes triées ») :
     *   - chaque fichier est lu trié par clé (fileGroups renvoie les groupes dans l'ordre) ;
     *   - on avance dans les deux listes en même temps, comme on fusionne deux
     *     paquets de fiches triées : si les clés sont égales, on compare les
     *     deux groupes ; sinon le groupe à la plus petite clé n'a pas de
     *     partenaire et ses lignes sont « exclusives ».
     *   On n'a ainsi jamais plus d'un groupe par côté en mémoire, même pour 80 000 lignes.
     *
     * @param  bool  $persistResults  faux = calcul des écarts seulement (aucun résultat ni statut modifié)
     */
    private function matchFiles(MatchingRule $rule, string $batchReference, int $importIdA, int $importIdB, bool $persistResults = true): MatchingRunSummary
    {
        $criteria = $rule->criteria;
        $primaryA = $criteria['primary_key']['a'];
        $primaryB = $criteria['primary_key']['b'];
        $verifyFields = $criteria['verify_fields'] ?? [];
        $excludedNonNumericA = $criteria['excluded_non_numeric']['a'] ?? [];
        $excludedNonNumericB = $criteria['excluded_non_numeric']['b'] ?? [];

        return DB::transaction(function () use ($rule, $batchReference, $importIdA, $importIdB, $primaryA, $primaryB, $verifyFields, $persistResults, $criteria, $excludedNonNumericA, $excludedNonNumericB) {
            // Serialize file jobs (including inverse pairs) before loading candidates.
            $imports = Import::query()->whereIn('id', [$importIdA, $importIdB])->orderBy('id')->lockForUpdate()->get();
            if ($imports->count() !== 2) {
                throw new \InvalidArgumentException('Un des fichiers a été supprimé.');
            }
            $run = null;
            if ($persistResults) {
                // Une comparaison (fichier A, fichier B, lot) n'est calculée qu'une fois :
                // si elle existe déjà avec un résumé, on renvoie ce résumé (voir plus bas).
                $run = ComparisonRun::firstOrCreate(
                    ['import_a_id' => $importIdA, 'import_b_id' => $importIdB, 'batch_reference' => $batchReference],
                    ['criteria' => $criteria],
                );
                if ($run->summary !== null) {
                    if ($run->invalidated_at !== null) {
                        throw new \RuntimeException('Les données ont été renormalisées : lancer une nouvelle comparaison avec un nouveau lot.');
                    }

                    return new MatchingRunSummary(...$run->summary);
                }
                $ids = NormalizedTransaction::query()->fromActiveImports()
                    ->whereHas('transaction', fn ($q) => $q->whereIn('import_id', [$importIdA, $importIdB]))->pluck('id');
                app(TransactionStatus::class)->lock($ids, false);
            }
            $started = now();
            // Cache des écarts de ce couple de fichiers : on le vide puis on le remplit à nouveau.
            $snapshot = UnmatchedSnapshot::firstOrCreate(['import_a_id' => $importIdA, 'import_b_id' => $importIdB], ['status' => 'processing']);
            DB::table('unmatched_snapshot_rows')->where('snapshot_id', $snapshot->id)->delete();
            $storage = app(SnapshotRows::class);
            // $a et $b sont des « générateurs » : ils fournissent les groupes un par un, triés par clé.
            $a = $this->fileGroups($rule->source_a_id, $primaryA, $importIdA, $excludedNonNumericA);
            $b = $this->fileGroups($rule->source_b_id, $primaryB, $importIdB, $excludedNonNumericB);
            $matched = $conflicts = $considered = 0;
            $exclusiveIds = ['a' => [], 'b' => []]; // id des lignes présentes d'un seul côté
            $buffers = ['a' => [], 'b' => []];      // lignes exclusives en attente d'écriture (par 500)
            $totals = [];   // nombre de lignes et montants par côté et par catégorie
            $expected = []; // totaux attendus, calculés directement en SQL pour le contrôle final
            foreach (['a' => $importIdA, 'b' => $importIdB] as $side => $importId) {
                $aggregate = NormalizedTransaction::query()->fromActiveImports()
                    ->whereHas('transaction', fn ($q) => $q->where('import_id', $importId))
                    ->selectRaw('COUNT(*) AS row_count, COALESCE(SUM(normalized_amount_millimes), 0) AS amount')->first();
                $expected[$side] = ['rows' => (int) $aggregate->row_count, 'amount_millimes' => (int) $aggregate->amount];
            }
            foreach (['a', 'b'] as $side) {
                foreach (['total', 'matched', 'conflict', 'exclusive'] as $category) {
                    $totals[$side][$category] = ['rows' => 0, 'amount_millimes' => 0];
                }
            }
            // Petite fonction locale qui ajoute des lignes au compteur d'une catégorie.
            $tally = function ($side, $category, $rows) use (&$totals) {
                $totals[$side][$category]['rows'] += $rows->count();
                $totals[$side][$category]['amount_millimes'] += (int) $rows->sum('normalized_amount_millimes');
            };
            // Boucle de fusion : tant qu'il reste un groupe d'un côté ou de l'autre.
            while ($a->valid() || $b->valid()) {
                // strcmp compare les deux clés : < 0 si la clé A est plus petite, 0 si égales, > 0 sinon.
                // Si un côté est épuisé, on traite l'autre seul.
                $comparison = ! $a->valid() ? 1 : (! $b->valid() ? -1 : strcmp($a->key(), $b->key()));
                // On prend le groupe du côté à la plus petite clé (ou les deux si égales) ; l'autre côté reste vide.
                $groupA = $comparison <= 0 ? $a->current() : collect();
                $groupB = $comparison >= 0 ? $b->current() : collect();
                if ($comparison <= 0) {
                    $a->next();
                }
                if ($comparison >= 0) {
                    $b->next();
                }
                $considered++;
                $tally('a', 'total', $groupA);
                $tally('b', 'total', $groupB);
                $identities = collect();
                foreach (['a' => $groupA, 'b' => $groupB] as $side => $rows) {
                    foreach ($rows as $row) {
                        $identities->push('key:'.$this->primaryKeyValue($row, $side === 'a' ? $primaryA : $primaryB));
                    }
                }
                // Contrôle de sécurité : toutes les lignes du groupe doivent avoir la même clé.
                if ($identities->uniqueStrict()->count() > 1) {
                    throw new \RuntimeException('Clés de comparaison incohérentes.');
                }
                // Dans un groupe, on sous-regroupe par « signature » (date + montant + champs vérifiés).
                // Deux lignes de même signature, une de chaque côté, forment une paire exacte.
                $fullA = $groupA->groupBy(fn ($nt) => $this->fileSignature($nt, 'a', $verifyFields));
                $fullB = $groupB->groupBy(fn ($nt) => $this->fileSignature($nt, 'b', $verifyFields));
                $remainingA = collect();
                $remainingB = collect();

                foreach ($fullA->keys()->merge($fullB->keys())->unique() as $signature) {
                    $rowsA = $fullA->get($signature, collect());
                    $rowsB = $fullB->get($signature, collect());
                    // Autant de paires que le plus petit des deux nombres : 3 lignes A et 2 lignes B
                    // identiques donnent 2 paires, et la 3e ligne A reste à examiner.
                    $count = min($rowsA->count(), $rowsB->count());
                    if ($count > 0) {
                        if ($persistResults) {
                            $this->persistOutcome($rowsA->take($count), $rowsB->take($count), $rule,
                                MatchingResultStatus::Matched, 100.0, null, $batchReference, $run->id);
                        }
                        $tally('a', 'matched', $rowsA->take($count));
                        $tally('b', 'matched', $rowsB->take($count));
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
                        $this->persistOutcome($remainingA, $remainingB, $rule, MatchingResultStatus::Conflict, null, $type, $batchReference, $run->id);
                    }
                    $tally('a', 'conflict', $remainingA);
                    $tally('b', 'conflict', $remainingB);
                    $conflicts++;
                } else {
                    // Il ne reste des lignes que d'un côté : elles sont « exclusives » (sans partenaire).
                    foreach (['a' => $remainingA, 'b' => $remainingB] as $side => $remaining) {
                        $tally($side, 'exclusive', $remaining);
                        foreach ($remaining as $nt) {
                            $exclusiveIds[$side][] = $nt->id;
                            $buffers[$side][] = $this->fileSnapshotRow($nt, $side === 'a' ? $primaryA : $primaryB);
                            if (count($buffers[$side]) >= 500) {
                                $storage->insert($snapshot->id, $side, $buffers[$side]);
                                $buffers[$side] = [];
                            }
                        }
                    }
                }
            }

            // Contrôle final (« bilan ») pour chaque côté :
            //   total lu == total attendu en SQL
            //   total == rapprochées + conflits + exclusives (en lignes ET en millimes)
            // Si ce n'est pas le cas, on annule tout plutôt que d'afficher des chiffres faux.
            foreach (['a', 'b'] as $side) {
                $storage->insert($snapshot->id, $side, $buffers[$side]);
                $totals[$side]['balanced'] =
                    $totals[$side]['total'] === $expected[$side]
                    && $totals[$side]['total']['rows'] === $totals[$side]['matched']['rows'] + $totals[$side]['conflict']['rows'] + $totals[$side]['exclusive']['rows']
                    && $totals[$side]['total']['amount_millimes'] === $totals[$side]['matched']['amount_millimes'] + $totals[$side]['conflict']['amount_millimes'] + $totals[$side]['exclusive']['amount_millimes'];
                if (! $totals[$side]['balanced']) {
                    throw new \RuntimeException('Bilan de comparaison déséquilibré.');
                }
            }
            $snapshot->update(['status' => 'completed', 'rows_persisted' => true, 'result_a' => null, 'result_b' => null,
                'file_totals' => $totals, 'error' => null, 'started_at' => $started, 'completed_at' => now()]);
            $summary = new MatchingRunSummary($considered, $matched, $conflicts, 0, 0, count($exclusiveIds['a']), count($exclusiveIds['b']));
            $run?->update(['summary' => (array) $summary, 'file_totals' => $totals,
                'unmatched_a_ids' => $exclusiveIds['a'], 'unmatched_b_ids' => $exclusiveIds['b']]);

            return $summary;
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

    /**
     * Read ordered groups in pages; only the current key and one page are retained.
     *
     * Fonctionnement, pour les curieux :
     *   1. On construit en SQL une expression qui calcule la clé de chaque ligne
     *      (ex. 'key:697464', ou 'missing:<id>' si un champ de la clé est vide :
     *      une ligne sans clé ne doit jamais être regroupée avec une autre).
     *   2. On stocke (id, empreinte SHA-256 de la clé) dans une table TEMPORAIRE indexée.
     *      L'empreinte a une longueur fixe et se trie de la même façon en MySQL et en SQLite.
     *   3. On relit cette table triée par clé, par pages de 1 000, et on renvoie
     *      (`yield`) chaque groupe de lignes de même clé.
     * La table temporaire est supprimée à la fin (bloc finally).
     */
    private function fileGroups(int $sourceId, string|array $primary, int $importId, array $excludedNonNumeric = []): \Generator
    {
        $fields = $primary === 'date|amount' ? ['date', 'amount'] : (array) $primary;
        // La « grammar » sait écrire une colonne JSON dans le dialecte SQL de la base (MySQL ou SQLite).
        $grammar = DB::connection()->getQueryGrammar();
        $sourceCode = strtoupper((string) DB::table('sources')->where('id', $sourceId)->value('code'));
        // Pour chaque champ de la clé : l'expression SQL qui lit sa valeur.
        $expressions = array_map(fn ($field) => match ($field) {
            'date' => 'normalized_transactions.normalized_date',
            'amount' => 'normalized_transactions.normalized_amount_millimes',
            'reference' => 'normalized_transactions.normalized_reference',
            // BNA : l'autorisation est la référence principale ; ALPHA : la secondaire.
            'num_autorisation' => $sourceCode === 'BNA'
                ? 'normalized_transactions.normalized_reference'
                : 'COALESCE(normalized_transactions.normalized_secondary_reference, NULLIF('.$grammar->wrap('transactions.raw_payload->num_autorisation').", 'null'))",
            'secondary_reference' => 'normalized_transactions.normalized_secondary_reference',
            default => 'NULLIF('.$grammar->wrap('transactions.raw_payload->'.$field).", 'null')",
        }, $fields);
        // Concaténation de textes : `a || b` en SQLite (tests), CONCAT(a, b) en MySQL.
        $concat = fn ($parts) => DB::getDriverName() === 'sqlite' ? implode(' || ', $parts) : 'CONCAT('.implode(', ', $parts).')';
        $parts = ["'key:'"];
        foreach ($expressions as $index => $expression) {
            if ($index > 0) {
                $parts[] = "'|'";
            }
            $parts[] = 'CAST('.$expression.' AS CHAR)';
        }
        $checks = array_map(fn ($expression) => "($expression IS NULL OR TRIM(CAST($expression AS CHAR)) = '')", $expressions);
        if (in_array('reference', $fields, true)) {
            $checks[] = "(transactions.external_reference IS NULL OR TRIM(transactions.external_reference) = '')";
        }
        $key = 'CASE WHEN '.implode(' OR ', $checks).' THEN '.$concat(["'missing:'", 'CAST(normalized_transactions.id AS CHAR)']).' ELSE '.$concat($parts).' END';
        $key = DB::getDriverName() === 'sqlite' ? "($key) COLLATE BINARY" : "CAST(($key) AS BINARY)";
        $query = NormalizedTransaction::query()->fromActiveImports()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->where('transactions.source_id', $sourceId)->where('transactions.import_id', $importId)
            ->select('normalized_transactions.id');
        $table = 'comparison_keys_'.bin2hex(random_bytes(8));
        $sqlite = DB::getDriverName() === 'sqlite';
        if ($sqlite) {
            DB::connection()->getPdo()->sqliteCreateFunction('comparison_sha256', fn ($value) => hash('sha256', (string) $value), 1);
            $query->selectRaw("comparison_sha256($key) AS comparison_key");
            DB::statement("CREATE TEMPORARY TABLE $table (id INTEGER PRIMARY KEY, comparison_key TEXT COLLATE BINARY NOT NULL)");
            DB::statement("CREATE INDEX {$table}_order ON $table (comparison_key, id)");
        } else {
            $query->selectRaw("SHA2($key, 256) AS comparison_key");
            // CREATE/DROP TEMPORARY TABLE do not commit a MySQL transaction.
            // Declare indexes here, never through ALTER TABLE / CREATE INDEX.
            DB::statement("CREATE TEMPORARY TABLE $table (id BIGINT UNSIGNED PRIMARY KEY, comparison_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, INDEX key_order (comparison_key, id)) ENGINE=InnoDB");
        }
        try {
            // Remplit la table temporaire en une seule requête INSERT ... SELECT, exécutée par la base.
            DB::statement("INSERT INTO $table (id, comparison_key) ".$query->toSql(), $query->getBindings());
            $previous = null;
            $group = collect();
            $pageSize = max(1, (int) config('matching.file_page_size', 1000));
            $lastKey = null;
            $lastId = null;
            // Pagination « par curseur » : chaque page reprend juste après la dernière
            // ligne lue (clé, id), plus rapide qu'un OFFSET sur de gros volumes.
            do {
                $page = DB::table($table)->when($lastKey !== null, fn ($q) => $q->where(fn ($q) => $q->where('comparison_key', '>', $lastKey)
                    ->orWhere(fn ($q) => $q->where('comparison_key', $lastKey)->where('id', '>', $lastId))))
                    ->orderBy('comparison_key')->orderBy('id')->limit($pageSize)->get();
                $rows = NormalizedTransaction::query()->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
                    ->with('transaction.source')->whereIn('normalized_transactions.id', $page->pluck('id'))
                    ->select('normalized_transactions.*', 'transactions.raw_payload')->get()->keyBy('id');
                foreach ($page as $entry) {
                    $row = $rows->get($entry->id);
                    if ($row === null) {
                        throw new \RuntimeException('Une transaction a changé pendant la comparaison.');
                    }
                    $entryKey = $entry->comparison_key;
                    // Une ligne dont un champ doit être numérique mais ne l'est pas reçoit
                    // une clé unique : elle restera seule, donc « exclusive ».
                    $current = $this->hasExcludedNonNumeric($row, $excludedNonNumeric)
                        ? $entryKey.'|missing:'.$row->id
                        : $entryKey;
                    // La clé change : le groupe précédent est complet, on le renvoie.
                    if ($previous !== null && $previous !== $current) {
                        yield $previous => $group;
                        $group = collect();
                    }
                    $group->push($row);
                    $previous = $current;
                    $lastKey = $entryKey;
                    $lastId = $row->id;
                }
            } while ($page->count() === $pageSize);
            if ($group->isNotEmpty()) {
                yield $previous => $group;
            }
        } finally {
            DB::statement(($sqlite ? 'DROP TABLE ' : 'DROP TEMPORARY TABLE ').$table);
        }
    }

    /** Vrai si l'un des champs donnés n'est pas composé uniquement de chiffres (ex. autorisation « ABC12 »). */
    private function hasExcludedNonNumeric(NormalizedTransaction $transaction, array $fields): bool
    {
        foreach ($fields as $field) {
            if (! preg_match('/^\d+$/', (string) $this->fieldValue($transaction, $field))) {
                return true;
            }
        }

        return false;
    }

    /**
     * « Signature » d'une ligne : date + montant + champs vérifiés croisés,
     * encodés en JSON. Deux lignes de même signature sont identiques pour la comparaison.
     * Si une valeur manque, la signature est unique ('missing:<id>') : la ligne ne peut pas être appariée.
     */
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

    /** Données d'une ligne exclusive, telles qu'elles sont stockées dans le cache des écarts et affichées. */
    private function fileSnapshotRow(NormalizedTransaction $nt, string|array $primaryKey): array
    {
        return [
            'id' => $nt->id,
            'source' => $nt->transaction->source->code,
            'reference' => $nt->normalized_reference,
            'num_autorisation' => $this->fieldValue($nt, 'num_autorisation'),
            'secondary_reference' => $this->fieldValue($nt, 'secondary_reference'),
            'amount_millimes' => $nt->normalized_amount_millimes,
            'date' => $nt->normalized_date?->format('d/m/Y'),
            'primary_key_value' => $this->primaryKeyValue($nt, $primaryKey),
        ];
    }

    /**
     * Charge les transactions d'une source et les regroupe par clé (mode « règle globale »).
     *
     * Filtres appliqués :
     *   - seulement les transactions non rapprochées (sauf si $allStatuses) ;
     *   - seulement l'import demandé, si $importId est fourni ;
     *   - sans les types bruts exclus (ex. « Commission » pour BNA) ;
     *   - sans les lignes dont un champ devant être numérique ne l'est pas.
     *
     * @return Collection<string,Collection<int,NormalizedTransaction>> keyed by the configured primary key
     */
    private function loadCandidates(int $sourceId, array $excludedStatusRaw, string|array $primaryKey, ?int $importId = null, bool $allStatuses = false, array $excludedNonNumeric = []): Collection
    {
        $rows = NormalizedTransaction::query()
            ->fromActiveImports()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->join('sources', 'sources.id', '=', 'transactions.source_id')
            ->where('transactions.source_id', $sourceId)
            ->when(! $allStatuses, fn ($query) => $query->where('normalized_transactions.matching_status', MatchingStatus::Unmatched->value))
            ->when($allStatuses, fn ($query) => $query->with('transaction.source'))
            ->when($importId !== null, fn ($query) => $query->where('transactions.import_id', $importId))
            ->when($excludedStatusRaw !== [], fn ($query) => $query->where(function ($inner) use ($excludedStatusRaw) {
                $inner->whereNull('transactions.raw_payload->status_raw')
                    ->orWhereNotIn('transactions.raw_payload->status_raw', $excludedStatusRaw);
            }))
            // On récupère aussi raw_payload et le code source en une seule requête (jointures),
            // pour ne pas refaire une requête par transaction ensuite.
            ->select('normalized_transactions.*', 'transactions.raw_payload', 'sources.code as transaction_source_code')
            ->get();

        if ($excludedNonNumeric !== []) {
            $rows = $rows->reject(function (NormalizedTransaction $nt) use ($excludedNonNumeric): bool {
                foreach ($excludedNonNumeric as $field) {
                    if (! preg_match('/^\d+$/', (string) $this->fieldValue($nt, $field))) {
                        return true;
                    }
                }

                return false;
            });
        }

        // groupBy : range les transactions dans des « tiroirs » selon la valeur renvoyée (la clé).
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
     *
     * Exemples : 'reference' -> '185113460' ; 'date|amount' -> '2026-05-01|989000' ;
     * ['reference', 'num_autorisation'] -> '185113460|697464'.
     */
    private function primaryKeyValue(NormalizedTransaction $nt, string|array $primaryKey): string
    {
        if ($primaryKey === 'date|amount') {
            return $nt->normalized_date?->format('Y-m-d').'|'.$nt->normalized_amount_millimes;
        }

        if ($primaryKey === 'reference') {
            return (string) $nt->normalized_reference;
        }

        if ($primaryKey === 'num_autorisation') {
            return (string) $this->fieldValue($nt, $primaryKey);
        }

        if ($primaryKey === 'secondary_reference') {
            return (string) $nt->normalized_secondary_reference;
        }

        if (is_array($primaryKey)) {
            $payload = $this->rawPayload($nt);
            $parts = [];

            foreach ($primaryKey as $field) {
                if ($field === 'date|amount') {
                    $parts[] = $nt->normalized_date->format('Y-m-d').'|'.$nt->normalized_amount_millimes;
                } elseif ($field === 'reference') {
                    $parts[] = (string) $nt->normalized_reference;
                } elseif ($field === 'num_autorisation') {
                    $parts[] = (string) $this->fieldValue($nt, $field);
                } elseif ($field === 'secondary_reference') {
                    $parts[] = (string) $nt->normalized_secondary_reference;
                } else {
                    $parts[] = (string) ($payload[$field] ?? '');
                }
            }

            return implode('|', $parts);
        }

        $payload = $this->rawPayload($nt);

        return (string) ($payload[$primaryKey] ?? '');
    }

    /** Renvoie raw_payload sous forme de tableau, qu'il ait été chargé en texte JSON ou déjà décodé. */
    private function rawPayload(NormalizedTransaction $nt): array
    {
        $payload = $nt->getAttribute('raw_payload');

        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?? [];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * Compare un groupe A et un groupe B de même clé, et enregistre le résultat (mode « règle globale »).
     *
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

        // Tout ce qui suit se fait dans une transaction SQL, avec les lignes verrouillées :
        // deux rapprochements lancés en même temps ne peuvent pas prendre la même transaction.
        return DB::transaction(function () use ($groupA, $groupB, $rule, $amountOk, $dateOk, $amountExact, $dateExact, $batchReference) {
            $allIds = $groupA->pluck('id')->merge($groupB->pluck('id'))->unique()->sort()->values();
            app(TransactionStatus::class)->lock($allIds);

            // Vérification défensive : un job concurrent peut avoir déjà
            // traité une partie de ce groupe pendant l'exécution.
            // MySQL has a 65 535 placeholder limit per prepared statement, so
            // large reference groups must be checked in chunks.
            $stillUnmatched = 0;
            foreach ($allIds->chunk(1000) as $chunk) {
                $stillUnmatched += NormalizedTransaction::query()
                    ->fromActiveImports()
                    ->whereIn('id', $chunk)
                    ->where('matching_status', MatchingStatus::Unmatched->value)
                    ->lockForUpdate()->get(['id'])->count();
            }

            if ($stillUnmatched !== $allIds->count()) {
                return 'skipped';
            }

            // Cas 1 : montant ET date dans la tolérance -> rapprochement (exact = matched, sinon partial).
            if ($amountOk && $dateOk) {
                $status = ($amountExact && $dateExact) ? MatchingResultStatus::Matched : MatchingResultStatus::Partial;
                $confidence = $this->scorer->score($amountExact, $dateExact);
                $this->persistOutcome($groupA, $groupB, $rule, $status, $confidence, null, $batchReference);

                return 'matched';
            }

            // Cas 2 : un seul des deux est bon (`xor` = « ou exclusif ») -> conflit + exception.
            if ($amountOk xor $dateOk) {
                $exceptionType = $dateOk ? ExceptionType::AmountMismatch : ExceptionType::DateMismatch;
                $this->persistOutcome($groupA, $groupB, $rule, MatchingResultStatus::Conflict, null, $exceptionType, $batchReference);

                return 'conflict';
            }

            // Cas 3 : ni le montant ni la date : simple coïncidence de clé, on ne fait rien.
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
     * Resolve a field value from a normalized transaction. 'amount', 'date',
     * 'num_autorisation' and 'secondary_reference' map to normalized columns;
     * everything else is read from the transaction's raw_payload.
     */
    private function fieldValue(NormalizedTransaction $nt, string $field): mixed
    {
        return match ($field) {
            'amount' => $nt->normalized_amount_millimes,
            'date' => $nt->normalized_date?->format('Y-m-d'),
            // BNA : l'autorisation est la référence principale ; ALPHA : la secondaire.
            'num_autorisation' => $this->sourceCode($nt) === 'BNA'
                ? $nt->normalized_reference
                : ($nt->normalized_secondary_reference ?? $this->payloadField($nt, $field)),
            'secondary_reference' => $nt->normalized_secondary_reference,
            default => $this->payloadField($nt, $field),
        };
    }

    /** Code de la source d'une transaction ('BNA', 'ALPHA'…), lu dans la jointure si disponible, sinon via la relation. */
    private function sourceCode(NormalizedTransaction $nt): string
    {
        return strtoupper((string) (
            $nt->getAttribute('transaction_source_code')
            ?? $nt->transaction?->source?->code
            ?? ''
        ));
    }

    /** Lit un champ dans raw_payload (null s'il est absent). */
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
        // Si les deux groupes contiennent exactement les mêmes couples (montant, date), tout est bon d'office.
        $exactMultisetMatch = $this->multisetsMatchExactly($groupA, $groupB);

        // Montant : on compare la SOMME de chaque groupe (ex. 1 paiement de 30 = 2 paiements de 10 + 20).
        $sumA = (int) $groupA->sum('normalized_amount_millimes');
        $sumB = (int) $groupB->sum('normalized_amount_millimes');
        $amountOk = $exactMultisetMatch || abs($sumA - $sumB) <= $toleranceAmount;
        $amountExact = $exactMultisetMatch || $sumA === $sumB;

        // Date : écart en jours entre la date la plus ancienne et la plus récente des deux groupes.
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

        // On transforme chaque transaction en texte "montant|date", on trie les deux listes, puis on les compare.
        $keyOf = fn (NormalizedTransaction $nt) => $nt->normalized_amount_millimes.'|'.$nt->normalized_date->format('Y-m-d');

        $bagA = $groupA->map($keyOf)->sort()->values();
        $bagB = $groupB->map($keyOf)->sort()->values();

        return $bagA->all() === $bagB->all();
    }

    /**
     * Enregistre un résultat de rapprochement :
     *   1. une ligne matching_results (le résultat) ;
     *   2. une ligne matching_details par transaction (côté a ou b), insérées par paquets de 1 000 ;
     *   3. le recalcul du statut des transactions concernées (TransactionStatus::refresh) ;
     *   4. une exception si le résultat est un conflit.
     *
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
        ?int $comparisonRunId = null,
    ): void {
        $result = MatchingResult::create([
            'comparison_run_id' => $comparisonRunId,
            'matching_rule_id' => $rule->id,
            'batch_reference' => $batchReference,
            'status' => $status,
            'confidence_score' => $confidence,
            'matched_by' => null,
            'matched_at' => now(),
            'notes' => $this->cardinalityNote($rule, $groupA->count(), $groupB->count()),
        ]);

        $now = now();
        foreach (['a' => $groupA, 'b' => $groupB] as $side => $group) {
            foreach ($group->chunk(1000) as $chunk) {
                MatchingDetail::insert($chunk->map(fn ($nt) => [
                    'matching_result_id' => $result->id,
                    'normalized_transaction_id' => $nt->id,
                    'side' => $side,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        }

        $allIds = $groupA->pluck('id')->merge($groupB->pluck('id'));
        app(TransactionStatus::class)->refresh($allIds);

        if ($exceptionType !== null) {
            ExceptionRecord::create([
                'normalized_transaction_id' => null,
                'matching_result_id' => $result->id,
                'type' => $exceptionType,
                'status' => ExceptionStatus::Open,
            ]);
        }
    }

    /**
     * Ajoute une note quand la forme réelle du groupe (ex. 1 ligne A pour 3 lignes B)
     * diffère de la cardinalité configurée sur la règle. Renvoie null s'il n'y a rien à signaler.
     */
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
