<?php

namespace App\Services\Matching;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\NormalizedTransaction;
use App\Models\ExceptionRecord;
use App\Enums\MatchingStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tient à jour le statut de rapprochement (matching_status) des transactions.
 *
 * Règle : le statut d'une transaction se DÉDUIT de ses résultats de
 * rapprochement actifs (non supprimés) :
 *   - au moins un résultat « conflict »      -> conflict
 *   - sinon au moins un « matched/partial »  -> matched
 *   - sinon                                  -> unmatched
 * On recalcule donc le statut après chaque création ou annulation de
 * résultat, au lieu de le modifier « à la main » à plusieurs endroits.
 */
class TransactionStatus
{
    /**
     * Verrouille des transactions (SELECT ... FOR UPDATE) pour qu'aucun autre
     * processus ne les modifie avant la fin de la transaction SQL en cours.
     *
     * Les id sont triés et traités par paquets de 1 000 : verrouiller toujours
     * dans le même ordre évite les « interblocages » (deadlocks) entre deux
     * processus qui s'attendraient mutuellement.
     *
     * Call inside a transaction, before reading or changing matching results.
     *
     * @param  bool  $retain  si faux, on verrouille sans garder les lignes en mémoire
     */
    public function lock(Collection $ids, bool $retain = true): Collection
    {
        $rows = collect();
        foreach ($ids->unique()->sort()->values()->chunk(1000) as $chunk) {
            $locked = NormalizedTransaction::query()->whereIn('id', $chunk)
                ->orderBy('id')->lockForUpdate()->get(['id', 'matching_status']);
            if ($retain) {
                $rows = $rows->concat($locked);
            }
        }

        return $rows;
    }

    /**
     * Recalcule le statut des transactions données selon la règle décrite en haut du fichier.
     *
     * Conflicts take precedence; rejected/deleted results do not reserve a row.
     */
    public function refresh(Collection $ids): void
    {
        foreach ($ids->unique()->chunk(1000) as $chunk) {
            // A locking read also sees commits made while waiting for row locks
            // under MySQL's default REPEATABLE READ isolation.
            // Pour chaque transaction, on note le « pire » statut parmi ses résultats :
            // conflict = 2, matched/partial = 1, autre = 0 (puis ->max() garde le plus grand).
            $statuses = DB::table('matching_details as d')
                ->join('matching_results as r', 'r.id', '=', 'd.matching_result_id')
                ->whereNull('r.deleted_at')->whereIn('d.normalized_transaction_id', $chunk)
                ->orderBy('d.id')->lockForUpdate()->get(['d.normalized_transaction_id', 'r.status'])
                ->groupBy('normalized_transaction_id')->map(fn ($rows) => $rows->max(fn ($row) => match ($row->status) {
                    'conflict' => 2, 'matched', 'partial' => 1, default => 0,
                }));

            // On regroupe les id par statut final, puis une seule requête UPDATE par statut.
            foreach ($chunk->groupBy(fn ($id) => match ((int) ($statuses[$id] ?? 0)) {
                2 => 'conflict', 1 => 'matched', default => 'unmatched',
            }) as $status => $group) {
                NormalizedTransaction::query()->whereIn('id', $group)->update(['matching_status' => $status]);
                // Une transaction enfin rapprochée n'est plus « non trouvée » :
                // on clôture automatiquement ses exceptions Unmatched encore ouvertes.
                if ($status === MatchingStatus::Matched->value) {
                    ExceptionRecord::query()->whereIn('normalized_transaction_id', $group)
                        ->where('type', ExceptionType::Unmatched->value)
                        ->where('status', ExceptionStatus::Open->value)
                        ->chunkById(500, function ($exceptions) {
                            foreach ($exceptions as $exception) {
                                $exception->update([
                                    'status' => ExceptionStatus::Resolved->value,
                                    'resolved_by' => null, // null = résolu par le système, pas par une personne
                                    'resolved_at' => now(),
                                    // `?:` garde le commentaire existant s'il n'est pas vide.
                                    'resolution_comment' => $exception->resolution_comment
                                        ?: 'Rapprochement effectué automatiquement.',
                                ]);
                            }
                        });
                }
            }
        }
    }
}
