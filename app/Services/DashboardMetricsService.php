<?php

namespace App\Services;

use App\Models\ExceptionRecord;
use App\Models\Import;
use App\Models\MatchingResult;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregate-only KPI queries for the dashboard. At real volumes (~320k
 * normalized_transactions rows, verified in Phase 3) loading a Collection
 * into PHP anywhere here would be wrong -- every method is a grouped
 * count/sum query, cached 5 minutes (same Cache::remember convention as
 * SettingsService).
 *
 * En clair : chaque chiffre du tableau de bord est calculé directement en SQL
 * (COUNT, SUM, GROUP BY) au lieu de charger des milliers de lignes en PHP.
 * Le résultat est gardé en cache 5 minutes : Cache::remember('clé', durée, fonction)
 * renvoie la valeur en cache si elle existe, sinon exécute la fonction et mémorise
 * son résultat. Conséquence : un nouvel import peut mettre jusqu'à 5 minutes à
 * apparaître sur le tableau de bord.
 */
class DashboardMetricsService
{
    /** Durée de vie du cache, en minutes. */
    private const TTL_MINUTES = 5;

    /** Imports : nombre par statut, total des lignes valides et en erreur, nombre ce mois-ci. */
    public function importStats(): array
    {
        return Cache::remember('dashboard.import_stats', now()->addMinutes(self::TTL_MINUTES), function () {
            // pluck('c', 'status') -> ['completed' => 12, 'failed' => 1, ...]
            $byStatus = Import::query()
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status');

            $totals = Import::query()
                ->selectRaw('coalesce(sum(success_rows), 0) as success, coalesce(sum(error_rows), 0) as errors')
                ->first();

            return [
                'by_status' => $byStatus,
                'success_rows' => (int) $totals->success,
                'error_rows' => (int) $totals->errors,
                'this_month' => Import::query()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            ];
        });
    }

    /** Résultats de rapprochement : nombre par statut (matched, partial, conflict…). */
    public function matchingStats(): array
    {
        return Cache::remember('dashboard.matching_stats', now()->addMinutes(self::TTL_MINUTES), function () {
            return MatchingResult::query()
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->all();
        });
    }

    /** Exceptions : nombre par type, par statut, et nombre encore ouvertes. */
    public function exceptionStats(): array
    {
        return Cache::remember('dashboard.exception_stats', now()->addMinutes(self::TTL_MINUTES), function () {
            $byType = ExceptionRecord::query()
                ->selectRaw('type, count(*) as c')
                ->groupBy('type')
                ->pluck('c', 'type');

            $byStatus = ExceptionRecord::query()
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status');

            return [
                'by_type' => $byType,
                'by_status' => $byStatus,
                'open' => (int) ($byStatus['open'] ?? 0),
            ];
        });
    }

    /** Par source : nombre de transactions et montant total (en millimes). */
    public function transactionVolumeBySource(): array
    {
        return Cache::remember('dashboard.transaction_volume_by_source', now()->addMinutes(self::TTL_MINUTES), function () {
            return Transaction::query()
                ->join('sources', 'sources.id', '=', 'transactions.source_id')
                ->selectRaw('sources.code as source_code, count(*) as c, coalesce(sum(transactions.amount_millimes), 0) as total_millimes')
                ->groupBy('sources.code')
                ->get()
                ->map(fn ($row) => [
                    'source_code' => $row->source_code,
                    'count' => (int) $row->c,
                    'total_millimes' => (int) $row->total_millimes,
                ])
                ->all();
        });
    }

    /**
     * Nombre de transactions par jour sur les 30 derniers jours de données (pour le graphique).
     * Les jours sans transaction sont ajoutés avec 0, pour que la courbe n'ait pas de trou.
     *
     * @return array<int,array{date:string,count:int}>
     */
    public function dailyTransactionTrend(int $days = 30): array
    {
        return Cache::remember("dashboard.daily_transaction_trend.{$days}", now()->addMinutes(self::TTL_MINUTES), function () use ($days) {
            // Use the max transaction_date available as anchor, since transaction_date
            // contains business dates (Feb-Jun 2026) while "now" is Sep 2026.
            $maxDate = Transaction::query()->max('transaction_date');

            // Aucune transaction en base : 30 jours à zéro, jusqu'à aujourd'hui.
            if (! $maxDate) {
                $trend = [];
                for ($i = 0; $i < $days; $i++) {
                    $date = now()->subDays($days - 1 - $i)->format('Y-m-d');
                    $trend[] = ['date' => $date, 'count' => 0];
                }

                return $trend;
            }

            $from = Carbon::parse($maxDate)->subDays($days - 1)->startOfDay();

            $rows = Transaction::query()
                ->where('transaction_date', '>=', $from->format('Y-m-d'))
                ->where('transaction_date', '<=', $maxDate)
                ->selectRaw('transaction_date, count(*) as c')
                ->groupBy('transaction_date')
                ->pluck('c', 'transaction_date');

            $trend = [];
            for ($i = 0; $i < $days; $i++) {
                $date = $from->copy()->addDays($i)->format('Y-m-d');
                $trend[] = ['date' => $date, 'count' => (int) ($rows[$date] ?? 0)];
            }

            return $trend;
        });
    }

    /** Nombre total de transactions importées. */
    public function totalTransactions(): int
    {
        return Cache::remember('dashboard.total_transactions', now()->addMinutes(self::TTL_MINUTES), fn () => Transaction::query()->count());
    }

    /** Nombre de sources actives. */
    public function activeSourceCount(): int
    {
        return Cache::remember('dashboard.active_source_count', now()->addMinutes(self::TTL_MINUTES), fn () => Source::query()->where('is_active', true)->count());
    }
}
