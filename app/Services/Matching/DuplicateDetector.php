<?php

namespace App\Services\Matching;

/**
 * Détecteur de doublons.
 *
 * Recherche les groupes de NormalizedTransaction partageant le même
 * dedup_hash (même source, référence, montant et date). La première ligne
 * (la plus ancienne par id) est considérée comme l'originale ; les
 * suivantes sont signalées comme doublons via ExceptionRecord. Ce scan
 * est déclenché manuellement, pas automatiquement après import.
 */
use App\DataTransferObjects\DuplicateScanSummary;
use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\ExceptionRecord;
use App\Models\NormalizedTransaction;

/**
 * Flags rows sharing a dedup_hash (same source, reference, amount and date)
 * beyond the first (oldest, by id) as duplicate exceptions. On-demand only,
 * not triggered automatically by an import or matching run.
 */
class DuplicateDetector
{
    public function scan(?int $sourceId = null): DuplicateScanSummary
    {
        $hashes = NormalizedTransaction::query()
            ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
            ->when($sourceId !== null, fn ($query) => $query->where('transactions.source_id', $sourceId))
            ->whereNotNull('normalized_transactions.dedup_hash')
            ->groupBy('normalized_transactions.dedup_hash')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('normalized_transactions.dedup_hash');

        $groupsFound = 0;
        $exceptionsCreated = 0;

        foreach ($hashes as $hash) {
            $groupsFound++;

            $rows = NormalizedTransaction::query()
                ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
                ->when($sourceId !== null, fn ($query) => $query->where('transactions.source_id', $sourceId))
                ->where('normalized_transactions.dedup_hash', $hash)
                ->orderBy('normalized_transactions.id')
                ->select('normalized_transactions.*')
                ->get();

            // La première ligne (la plus ancienne) est l'originale ;
            // chaque ligne suivante partageant le même hash est un doublon.
            foreach ($rows->slice(1) as $duplicate) {
                $alreadyFlagged = ExceptionRecord::query()
                    ->where('normalized_transaction_id', $duplicate->id)
                    ->where('type', ExceptionType::Duplicate->value)
                    ->exists();

                if ($alreadyFlagged) {
                    continue;
                }

                ExceptionRecord::create([
                    'normalized_transaction_id' => $duplicate->id,
                    'matching_result_id' => null,
                    'type' => ExceptionType::Duplicate,
                    'status' => ExceptionStatus::Open,
                ]);

                $exceptionsCreated++;
            }
        }

        return new DuplicateScanSummary($groupsFound, $exceptionsCreated);
    }
}
