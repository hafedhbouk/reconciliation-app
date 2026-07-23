<?php

namespace App\Services\Matching;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\MatchingStatus;
use App\Models\NormalizedTransaction;
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
    public function sweep(?int $sourceId = null): int
    {
        $chunkSize = config('matching.chunk_size', 1000);
        $created = 0;

        NormalizedTransaction::query()
            ->when($sourceId !== null, fn ($query) => $query->whereHas(
                'transaction',
                fn ($inner) => $inner->where('source_id', $sourceId),
            ))
            ->where('matching_status', MatchingStatus::Unmatched->value)
            ->whereDoesntHave('exceptions', fn ($query) => $query->whereIn('status', [
                ExceptionStatus::Open->value,
                ExceptionStatus::InReview->value,
            ]))
            ->select('normalized_transactions.id')
            ->chunkById($chunkSize, function ($rows) use (&$created) {
                $now = now();

                $inserts = $rows->map(fn ($row) => [
                    'normalized_transaction_id' => $row->id,
                    'matching_result_id' => null,
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
}
