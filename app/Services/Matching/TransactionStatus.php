<?php

namespace App\Services\Matching;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\NormalizedTransaction;
use App\Models\ExceptionRecord;
use App\Enums\MatchingStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionStatus
{
    /** Call inside a transaction, before reading or changing matching results. */
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

    /** Conflicts take precedence; rejected/deleted results do not reserve a row. */
    public function refresh(Collection $ids): void
    {
        foreach ($ids->unique()->chunk(1000) as $chunk) {
            // A locking read also sees commits made while waiting for row locks
            // under MySQL's default REPEATABLE READ isolation.
            $statuses = DB::table('matching_details as d')
                ->join('matching_results as r', 'r.id', '=', 'd.matching_result_id')
                ->whereNull('r.deleted_at')->whereIn('d.normalized_transaction_id', $chunk)
                ->orderBy('d.id')->lockForUpdate()->get(['d.normalized_transaction_id', 'r.status'])
                ->groupBy('normalized_transaction_id')->map(fn ($rows) => $rows->max(fn ($row) => match ($row->status) {
                    'conflict' => 2, 'matched', 'partial' => 1, default => 0,
                }));

            foreach ($chunk->groupBy(fn ($id) => match ((int) ($statuses[$id] ?? 0)) {
                2 => 'conflict', 1 => 'matched', default => 'unmatched',
            }) as $status => $group) {
                NormalizedTransaction::query()->whereIn('id', $group)->update(['matching_status' => $status]);
                if ($status === MatchingStatus::Matched->value) {
                    ExceptionRecord::query()->whereIn('normalized_transaction_id', $group)
                        ->where('type', ExceptionType::Unmatched->value)
                        ->where('status', ExceptionStatus::Open->value)
                        ->chunkById(500, function ($exceptions) {
                            foreach ($exceptions as $exception) {
                                $exception->update([
                                    'status' => ExceptionStatus::Resolved->value,
                                    'resolved_by' => null,
                                    'resolved_at' => now(),
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
