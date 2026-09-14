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
        $seen = [];
        $duplicateGroups = [];
        $exceptionsCreated = 0;

        NormalizedTransaction::query()->fromActiveImports()->with('transaction.source')
            ->when($sourceId !== null, fn ($query) => $query->whereHas('transaction', fn ($q) => $q->where('source_id', $sourceId)))
            ->whereNotNull('dedup_hash')->chunkById(1000, function ($rows) use (&$seen, &$duplicateGroups, &$exceptionsCreated) {
                foreach ($rows as $row) {
                    $transaction = $row->transaction;
                    $code = strtoupper($transaction->source->code);
                    $key = $transaction->source_id.'|'.$row->dedup_hash;
                    if ($code === 'BNA') {
                        // Use the actual authorization even for legacy imports whose
                        // stored hash was based on date/amount alone.
                        $authorization = $transaction->raw_payload['num_autorisation'] ?? null;
                        if ($authorization === null || trim((string) $authorization) === '') {
                            continue;
                        }
                        $key = json_encode([$transaction->source_id, (string) $authorization,
                            $row->normalized_date?->format('Y-m-d'), $row->normalized_amount_millimes], JSON_THROW_ON_ERROR);
                    }
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;

                        continue;
                    }
                    $duplicateGroups[$key] = true;
                    if (ExceptionRecord::query()->where('normalized_transaction_id', $row->id)
                        ->where('type', ExceptionType::Duplicate->value)->exists()) {
                        continue;
                    }
                    ExceptionRecord::create([
                        'normalized_transaction_id' => $row->id,
                        'matching_result_id' => null,
                        'type' => ExceptionType::Duplicate,
                        'status' => ExceptionStatus::Open,
                        'resolution_comment' => $code === 'SMT'
                            ? 'Doublon potentiel : la date et le montant seuls ne prouvent pas un paiement en double.' : null,
                    ]);
                    $exceptionsCreated++;
                }
            });

        return new DuplicateScanSummary(count($duplicateGroups), $exceptionsCreated);
    }
}
