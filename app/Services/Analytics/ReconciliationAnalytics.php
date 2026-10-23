<?php

namespace App\Services\Analytics;

use App\Enums\MatchingStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Indicateurs, évolution et tableau croisé du rapprochement, sur les lignes
 * réellement importées (normalized_transactions), imports actifs seulement.
 *
 * Pourquoi un « cube » ? Relire 320 000 lignes (jointes à transactions, dont
 * la colonne JSON raw_payload est volumineuse) prend plusieurs secondes à
 * chaque requête. On fait donc UNE agrégation SQL par jour × source × statut
 * × canal × fichier (quelques centaines de lignes), mise en cache, puis
 * tous les filtres et regroupements s'appliquent en PHP sur ces agrégats :
 * les chiffres restent exacts, le tableau de bord répond instantanément.
 *
 * Le cache est invalidé automatiquement dès que les données changent
 * (empreinte : nombre et dernier id des lignes, dernières modifications des
 * lignes et des imports) et au plus tard après CACHE_MINUTES ; cube(fresh: true)
 * force le recalcul (bouton « Actualiser »).
 *
 * Un pourcentage dont le total de référence vaut 0 est null (affiché « — »).
 */
class ReconciliationAnalytics
{
    /** Dimensions autorisées pour le tableau croisé (liste blanche). */
    public const DIMENSIONS = [
        'annee' => 'Année',
        'mois' => 'Mois',
        'jour' => 'Jour',
        'source' => 'Source',
        'statut' => 'Statut de rapprochement',
        'canal' => 'Canal',
        'fichier' => 'Fichier importé',
    ];

    public const MEASURES = ['lignes' => 'Nombre de lignes', 'montant' => 'Montant (TND)'];

    /** Au-delà, le tableau croisé est tronqué (l'interface le signale). */
    public const MAX_PIVOT_KEYS = 200;

    /** Durée maximale de conservation du cube. */
    public const CACHE_MINUTES = 10;

    /** @var array{calcule_le:string,lignes:Collection}|null cube de la requête en cours */
    private ?array $cube = null;

    /**
     * Agrégats jour × source × statut × canal × fichier (imports actifs).
     *
     * @return array{calcule_le:string,lignes:Collection}
     */
    public function cube(bool $fresh = false): array
    {
        if ($this->cube !== null && ! $fresh) {
            return $this->cube;
        }
        $key = 'analytics.reconciliation_cube.'.$this->version();
        if ($fresh) {
            Cache::forget($key);
        }
        $cube = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), fn () => [
            'calcule_le' => now()->toIso8601String(),
            'lignes' => DB::table('normalized_transactions as nt')
                ->join('transactions as t', 't.id', '=', 'nt.transaction_id')
                ->join('sources as s', 's.id', '=', 't.source_id')
                ->leftJoin('imports as i', 'i.id', '=', 't.import_id')
                ->where(fn ($q) => $q->whereNull('t.import_id')->orWhere(fn ($q) => $q->whereNotNull('i.id')->whereNull('i.deleted_at')))
                ->selectRaw('nt.normalized_date AS jour, s.code AS source, nt.matching_status AS statut, t.canal AS canal,
                    t.import_id AS import_id, i.original_filename AS fichier,
                    COUNT(*) AS lignes, COALESCE(SUM(nt.normalized_amount_millimes), 0) AS montant')
                ->groupBy('nt.normalized_date', 's.code', 'nt.matching_status', 't.canal', 't.import_id', 'i.original_filename')
                ->get()
                ->map(fn ($row) => [
                    'jour' => substr((string) $row->jour, 0, 10),
                    'source' => (string) $row->source,
                    'statut' => (string) $row->statut,
                    'canal' => ($row->canal ?? '') === '' ? '—' : (string) $row->canal,
                    'fichier' => $row->import_id === null ? '—' : (string) $row->import_id,
                    'nom_fichier' => $row->fichier ?? '—',
                    'lignes' => (int) $row->lignes,
                    'montant' => (int) $row->montant,
                ])->all(),
        ]);

        return $this->cube = ['calcule_le' => $cube['calcule_le'], 'lignes' => collect($cube['lignes'])];
    }

    /** Indicateurs principaux. @param array<string,mixed> $filters (voir DashboardController::analytics) */
    public function kpis(array $filters): array
    {
        $rows = $this->filtered($filters);
        $count = fn (array $statuses) => (int) $rows->whereIn('statut', $statuses)->sum('lignes');
        $total = (int) $rows->sum('lignes');
        $matched = $count([MatchingStatus::Matched->value, MatchingStatus::Partial->value]);
        $conflicts = $count([MatchingStatus::Conflict->value]);

        return [
            'lignes' => $total,
            'montant_millimes' => (int) $rows->sum('montant'),
            'rapprochees' => $matched,
            'conflits' => $conflicts,
            'non_rapprochees' => $count([MatchingStatus::Unmatched->value]),
            'ignorees' => $count([MatchingStatus::Ignored->value]),
            'taux_rapprochement' => self::percent($matched, $total),
            'taux_anomalie' => self::percent($conflicts, $total),
            'par_statut' => $this->statuses()->map(fn (string $label, string $status) => [
                'statut' => $status,
                'libelle' => $label,
                'lignes' => $count([$status]),
                'pourcentage' => self::percent($count([$status]), $total),
            ])->values()->all(),
        ];
    }

    /** Évolution par jour (période de 3 mois au plus) ou par mois, une série par statut. */
    public function timeline(array $filters): array
    {
        $rows = $this->filtered($filters);
        $days = $rows->pluck('jour')->sort()->values();
        $granularity = $days->isNotEmpty() && Carbon::parse($days->first())->diffInDays(Carbon::parse($days->last())) <= 92 ? 'jour' : 'mois';
        $periods = $rows->map(fn ($row) => $this->value($granularity, $row))->unique()->sort()->values();

        return [
            'granularite' => $granularity,
            'periodes' => $periods->map(fn ($key) => $this->label($granularity, $key))->all(),
            'series' => $this->statuses()
                ->filter(fn (string $label, string $status) => $rows->contains('statut', $status))
                ->map(fn (string $label, string $status) => [
                    'statut' => $status,
                    'libelle' => $label,
                    'valeurs' => $periods->map(fn ($key) => (int) $rows
                        ->filter(fn ($row) => $row['statut'] === $status && $this->value($granularity, $row) === $key)
                        ->sum('lignes'))->all(),
                ])->values()->all(),
        ];
    }

    /**
     * Tableau croisé : lignes × colonnes (facultatives), avec totaux et pourcentages
     * (du total général, et de la ligne).
     */
    public function pivot(array $filters, string $rowDimension, ?string $columnDimension, string $measure): array
    {
        $rows = $this->filtered($filters);
        $field = $measure === 'montant' ? 'montant' : 'lignes';
        $cells = [];
        foreach ($rows as $row) {
            $r = $this->value($rowDimension, $row);
            $c = $columnDimension ? $this->value($columnDimension, $row) : 'total';
            $cells[$r][$c] = ($cells[$r][$c] ?? 0) + $row[$field];
        }
        $rowKeys = $this->ordered($rowDimension, collect(array_keys($cells)));
        $columnKeys = $this->ordered((string) $columnDimension, collect($cells)->flatMap(fn ($columns) => array_keys($columns))->unique());
        $truncated = $rowKeys->count() > self::MAX_PIVOT_KEYS || $columnKeys->count() > self::MAX_PIVOT_KEYS;
        $rowKeys = $rowKeys->take(self::MAX_PIVOT_KEYS);
        $columnKeys = $columnKeys->take(self::MAX_PIVOT_KEYS);

        $total = (int) $rows->sum($field);
        $rowTotals = $rowKeys->mapWithKeys(fn ($r) => [$r => (int) array_sum($cells[$r])]);
        $columnTotals = $columnKeys->mapWithKeys(fn ($c) => [$c => (int) collect($cells)->sum(fn ($columns) => $columns[$c] ?? 0)]);
        $files = $rows->pluck('nom_fichier', 'fichier');

        return [
            'lignes' => $rowKeys->map(fn ($r) => ['cle' => $r, 'libelle' => $this->label($rowDimension, $r, $files)])->all(),
            'colonnes' => $columnDimension
                ? $columnKeys->map(fn ($c) => ['cle' => $c, 'libelle' => $this->label($columnDimension, $c, $files)])->all()
                : [['cle' => 'total', 'libelle' => self::MEASURES[$measure]]],
            'cellules' => $rowKeys->map(fn ($r) => $columnKeys->map(fn ($c) => [
                'valeur' => (int) ($cells[$r][$c] ?? 0),
                'pct_total' => self::percent($cells[$r][$c] ?? 0, $total),
                'pct_ligne' => self::percent($cells[$r][$c] ?? 0, $rowTotals[$r]),
            ])->all())->all(),
            'totaux_lignes' => $rowKeys->map(fn ($r) => [
                'valeur' => $rowTotals[$r], 'pct_total' => self::percent($rowTotals[$r], $total), 'pct_ligne' => self::percent($rowTotals[$r], $rowTotals[$r]),
            ])->all(),
            'totaux_colonnes' => $columnKeys->map(fn ($c) => [
                'valeur' => $columnTotals[$c], 'pct_total' => self::percent($columnTotals[$c], $total),
            ])->all(),
            'total' => $total,
            'mesure' => $measure,
            'tronque' => $truncated,
        ];
    }

    /** Valeurs proposées dans les filtres (années et sources présentes dans les données). */
    public function options(): array
    {
        // Requêtes légères (index de normalized_date, table sources) : la page s'affiche sans attendre le cube.
        $year = DB::getDriverName() === 'sqlite' ? "strftime('%Y', normalized_date)" : 'YEAR(normalized_date)';

        return [
            'annees' => DB::table('normalized_transactions')->whereNotNull('normalized_date')
                ->selectRaw("DISTINCT {$year} AS annee")->pluck('annee')->map(fn ($y) => (int) $y)->sortDesc()->values()->all(),
            'sources' => DB::table('sources')->orderBy('code')->pluck('code')->all(),
            'statuts' => $this->statuses()->map(fn ($label, $status) => ['valeur' => $status, 'libelle' => $label])->values()->all(),
            'dimensions' => self::DIMENSIONS,
            'mesures' => self::MEASURES,
        ];
    }

    /** Pourcentage arrondi à 0,1, ou null si le total de référence est nul. */
    public static function percent(int|float $part, int|float $total): ?float
    {
        if ($total <= 0) {
            return null;
        }
        $percent = $part * 100 / $total;

        // Un taux très faible mais non nul (9 conflits sur 318 890 lignes) ne doit pas s'afficher « 0 % ».
        return round($percent, $percent > 0 && $percent < 1 ? 3 : 1);
    }

    /** Empreinte des données : change dès qu'une ligne ou un import est ajouté, modifié ou supprimé. */
    private function version(): string
    {
        $lines = DB::table('normalized_transactions')->selectRaw('COUNT(*) AS n, MAX(id) AS last_id, MAX(updated_at) AS updated')->first();
        // Nombre de lignes par statut (index matching_status) : capte un rapprochement fait dans la même seconde.
        $statuses = DB::table('normalized_transactions')->selectRaw('matching_status, COUNT(*) AS n')
            ->groupBy('matching_status')->orderBy('matching_status')->pluck('n', 'matching_status');
        $imports = DB::table('imports')->selectRaw('COUNT(*) AS n, MAX(updated_at) AS updated, MAX(deleted_at) AS deleted')->first();

        return md5(json_encode([$lines, $statuses, $imports]));
    }

    /** Agrégats du cube correspondant aux filtres. */
    private function filtered(array $filters): Collection
    {
        $year = isset($filters['annee']) ? (string) $filters['annee'] : null;

        return $this->cube()['lignes']
            ->when($year, fn ($rows) => $rows->filter(fn ($row) => str_starts_with($row['jour'], $year)))
            ->when($filters['date_debut'] ?? null, fn ($rows, $from) => $rows->filter(fn ($row) => $row['jour'] >= $from))
            ->when($filters['date_fin'] ?? null, fn ($rows, $to) => $rows->filter(fn ($row) => $row['jour'] <= $to))
            ->when($filters['sources'] ?? [], fn ($rows, $codes) => $rows->whereIn('source', $codes))
            ->when($filters['statuts'] ?? [], fn ($rows, $statuses) => $rows->whereIn('statut', $statuses))
            ->values();
    }

    /** Valeur d'une dimension pour un agrégat (clé de regroupement). */
    private function value(string $dimension, array $row): string
    {
        return match ($dimension) {
            'annee' => substr($row['jour'], 0, 4),
            'mois' => substr($row['jour'], 0, 7),
            'jour' => $row['jour'],
            'source', 'statut', 'canal', 'fichier' => $row[$dimension],
            default => throw new \InvalidArgumentException("Dimension inconnue : {$dimension}"),
        };
    }

    /** Libellé affiché d'une valeur de dimension (mois en toutes lettres, statut traduit…). */
    /** Ordre d'affichage : statuts dans l'ordre habituel de l'application, autres valeurs triées. */
    private function ordered(string $dimension, Collection $keys): Collection
    {
        $keys = $keys->map(fn ($key) => (string) $key);
        if ($dimension !== 'statut') {
            return $keys->sort()->values();
        }
        $order = array_flip(array_map(fn (MatchingStatus $status) => $status->value, MatchingStatus::cases()));

        return $keys->sortBy(fn ($key) => $order[$key] ?? PHP_INT_MAX)->values();
    }

    private function label(string $dimension, string $key, ?Collection $files = null): string
    {
        return match (true) {
            $key === '' || $key === '—' => '—',
            $dimension === 'mois' => Carbon::createFromFormat('Y-m-d', $key.'-01')->locale(app()->getLocale())->translatedFormat('F Y'),
            $dimension === 'jour' => Carbon::parse($key)->format('d/m/Y'),
            $dimension === 'statut' => MatchingStatus::tryFrom($key)?->label() ?? $key,
            // Deux imports peuvent porter le même nom de fichier : on précise alors le numéro.
            $dimension === 'fichier' => ($name = (string) ($files[$key] ?? $key))
                .($files !== null && $files->filter(fn ($n) => $n === $name)->count() > 1 ? " (import n° {$key})" : ''),
            default => $key,
        };
    }

    /** Statuts de rapprochement, avec leur libellé français. */
    private function statuses(): Collection
    {
        return collect(MatchingStatus::cases())->mapWithKeys(fn (MatchingStatus $status) => [$status->value => $status->label()]);
    }
}
