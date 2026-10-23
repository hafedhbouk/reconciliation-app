<?php

namespace App\Services\Analytics;

use App\Models\Operation;
use App\Support\DateDisplay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Statistiques des opérations suivies (imports, rapprochements, comparaisons,
 * exports), à partir de la table operations (voir docs/21-suivi-des-operations.md).
 *
 * La période porte sur la date de LANCEMENT : un jour de l'utilisateur
 * (fuseau d'affichage, Africa/Tunis) est converti en bornes UTC pour
 * interroger la base, où les dates sont stockées en UTC.
 * $userId = null : toutes les opérations (réservé aux administrateurs,
 * vérifié par le contrôleur).
 */
class OperationAnalytics
{
    public function summary(array $filters, ?int $userId): array
    {
        $byTypeAndStatus = $this->base($filters, $userId)
            ->selectRaw('type, status, COUNT(*) AS n')->groupBy('type', 'status')->get();
        $count = fn (?string $type, ?string $status) => (int) $byTypeAndStatus
            ->when($type, fn ($rows) => $rows->where('type', $type))
            ->when($status, fn ($rows) => $rows->where('status', $status))->sum('n');
        $durations = $this->base($filters, $userId)->where('status', 'completed')
            ->whereNotNull('started_at')->whereNotNull('finished_at')->get(['type', 'started_at', 'finished_at'])
            ->groupBy('type')->map(fn ($rows) => (int) round($rows->avg(fn ($op) => $op->started_at->diffInSeconds($op->finished_at, true))));

        return [
            'total' => $count(null, null),
            'en_cours' => $count(null, 'pending') + $count(null, 'running'),
            'terminees' => $count(null, 'completed'),
            'echecs' => $count(null, 'failed'),
            // Taux de succès sur les opérations finies (terminées ou en échec).
            'taux_succes' => ReconciliationAnalytics::percent($count(null, 'completed'), $count(null, 'completed') + $count(null, 'failed')),
            'par_type' => collect(Operation::TYPES)->map(fn (string $label, string $type) => [
                'type' => $type,
                'libelle' => $label,
                'total' => $count($type, null),
                'terminees' => $count($type, 'completed'),
                'echecs' => $count($type, 'failed'),
                'duree_moyenne_secondes' => $durations[$type] ?? null,
            ])->filter(fn ($row) => $row['total'] > 0)->values()->all(),
            'derniers_echecs' => $this->base($filters, $userId)->where('status', 'failed')->with('user')
                ->latest('id')->limit(5)->get()->map(fn (Operation $op) => [
                    'libelle' => $op->label,
                    'type' => Operation::TYPES[$op->type] ?? $op->type,
                    'date' => DateDisplay::dateTime($op->finished_at ?? $op->created_at),
                    'erreur' => $op->error_message,
                    'utilisateur' => $op->user?->name,
                ])->all(),
        ];
    }

    private function base(array $filters, ?int $userId): Builder
    {
        $timezone = DateDisplay::timezone();
        $bound = fn (string $date, bool $end) => ($end ? Carbon::parse($date, $timezone)->endOfDay() : Carbon::parse($date, $timezone)->startOfDay())
            ->setTimezone(config('app.timezone'));

        return Operation::query()
            ->when($userId !== null, fn (Builder $q) => $q->where('user_id', $userId))
            ->when($filters['types'] ?? [], fn (Builder $q, $types) => $q->whereIn('type', $types))
            ->when($filters['annee'] ?? null, fn (Builder $q, $year) => $q
                ->where('created_at', '>=', $bound("{$year}-01-01", false))->where('created_at', '<=', $bound("{$year}-12-31", true)))
            ->when($filters['date_debut'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', $bound($date, false)))
            ->when($filters['date_fin'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<=', $bound($date, true)));
    }
}
