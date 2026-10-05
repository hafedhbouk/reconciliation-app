<?php

namespace App\Services\Matching;

/**
 * Balayeur des transactions non rapprochées.
 *
 * Dernière étape d'un batch de matching : toute normalized_transaction
 * toujours en statut Unmatched se voit créer une exception de type
 * Unmatched, sauf si une exception ouverte/en revue existe déjà pour
 * cette ligne. L'insertion est massique (query builder) pour supporter
 * des volumes importants sans saturer la mémoire.
 */
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\MatchingStatus;
use App\Models\NormalizedTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Final step of a matching run: every normalized_transaction still unmatched
 * after all rules (and duplicate detection) have run gets exactly one
 * unmatched-type exception, unless it already has an open/in-review one.
 * Bulk-inserted (bypassing Eloquent events, same convention as
 * ProcessImportJob's bulk writes) since a real sweep can touch thousands of
 * rows at once.
 */
class UnmatchedSweeper
{
    public function previewCount(?int $sourceId = null, ?int $importId = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        return $this->candidates($sourceId, $importId, $dateFrom, $dateTo)->count();
    }

    public function sweep(
        ?int $sourceId = null,
        ?int $importId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $batchReference = null,
    ): int
    {
        $chunkSize = config('matching.chunk_size', 1000);
        $created = 0;

        $this->candidates($sourceId, $importId, $dateFrom, $dateTo)
            ->select('normalized_transactions.id')
            ->chunkById($chunkSize, function ($rows) use (&$created, $batchReference) {
                $now = now();

                $inserts = $rows->map(fn ($row) => [
                    'normalized_transaction_id' => $row->id,
                    'matching_result_id' => null,
                    'batch_reference' => $batchReference,
                    'type' => ExceptionType::Unmatched->value,
                    'status' => ExceptionStatus::Open->value,
                    'created_by' => null,
                    'updated_by' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('exceptions')->insert($inserts);
                $created += count($inserts);
            });

        return $created;
    }

    private function candidates(?int $sourceId, ?int $importId, ?string $dateFrom, ?string $dateTo): Builder
    {
        return NormalizedTransaction::query()
            ->fromActiveImports()
            ->when($sourceId !== null, fn ($query) => $query->whereHas(
                'transaction',
                fn ($inner) => $inner->where('source_id', $sourceId),
            ))
            ->when($importId !== null, fn ($query) => $query->whereHas(
                'transaction',
                fn ($inner) => $inner->where('import_id', $importId),
            ))
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('normalized_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('normalized_date', '<=', $dateTo))
            ->where('matching_status', MatchingStatus::Unmatched->value)
            ->whereDoesntHave('exceptions', fn ($query) => $query->where(function ($inner) {
                $inner->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::InReview->value])
                    ->orWhere('type', ExceptionType::Unmatched->value);
            }));
    }
}
