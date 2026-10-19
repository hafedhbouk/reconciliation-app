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
    /**
     * Parcourt les transactions et crée une exception « Doublon » pour chaque copie trouvée.
     * Tous les filtres sont facultatifs (null = pas de filtre).
     *
     * @param  bool  $preview  si vrai, compte seulement, sans rien créer (aperçu avant confirmation)
     * @param  string|null  $batchReference  identifiant du lot, enregistré sur les exceptions créées
     */
    public function scan(
        ?int $sourceId = null,
        ?int $importId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        bool $preview = false,
        ?string $batchReference = null,
    ): DuplicateScanSummary {
        $seen = [];            // clés déjà rencontrées : la première occurrence est « l'originale »
        $duplicateGroups = []; // clés qui apparaissent au moins deux fois
        $exceptionsCreated = 0;

        // ->when(condition, fn) n'ajoute le filtre que si la condition est vraie.
        // chunkById(1000, ...) lit les lignes par paquets de 1 000, triés par id (donc la plus ancienne d'abord).
        // `use (&$seen, ...)` : le & permet à la fonction de MODIFIER ces variables extérieures.
        NormalizedTransaction::query()->fromActiveImports()->with('transaction.source')
            ->when($sourceId !== null, fn ($query) => $query->whereHas('transaction', fn ($q) => $q->where('source_id', $sourceId)))
            ->when($importId !== null, fn ($query) => $query->whereHas('transaction', fn ($q) => $q->where('import_id', $importId)))
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('normalized_date', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('normalized_date', '<=', $dateTo))
            ->whereNotNull('dedup_hash')->chunkById(1000, function ($rows) use (&$seen, &$duplicateGroups, &$exceptionsCreated, $preview, $batchReference) {
                foreach ($rows as $row) {
                    $transaction = $row->transaction;
                    $code = strtoupper($transaction->source->code);
                    // Clé d'identité : source + empreinte.
                    $key = $transaction->source_id.'|'.$row->dedup_hash;
                    if ($code === 'BNA') {
                        // Use the actual authorization even for legacy imports whose
                        // stored hash was based on date/amount alone.
                        $authorization = $transaction->raw_payload['num_autorisation'] ?? null;
                        if ($authorization === null || trim((string) $authorization) === '') {
                            continue; // sans autorisation, on ne peut rien conclure : ligne ignorée
                        }
                        $key = json_encode([$transaction->source_id, (string) $authorization,
                            $row->normalized_date?->format('Y-m-d'), $row->normalized_amount_millimes], JSON_THROW_ON_ERROR);
                    }
                    // Première fois qu'on voit cette clé : c'est l'originale, on passe.
                    if (! isset($seen[$key])) {
                        $seen[$key] = true;

                        continue;
                    }
                    $duplicateGroups[$key] = true;
                    // Exception déjà créée lors d'un scan précédent : on n'en crée pas une deuxième.
                    if (ExceptionRecord::query()->where('normalized_transaction_id', $row->id)
                        ->where('type', ExceptionType::Duplicate->value)->exists()) {
                        continue;
                    }
                    // Pour SMT, « même date et même montant » est un indice faible : le message le précise.
                    $reviewComment = $code === 'SMT'
                        ? 'Doublon potentiel : la date et le montant seuls ne prouvent pas un paiement en double. Vérification manuelle requise.'
                        : 'Doublon potentiel : mêmes identifiants et mêmes données de paiement. Vérification manuelle requise.';
                    if ($preview) {
                        $exceptionsCreated++;

                        continue;
                    }
                    ExceptionRecord::create([
                        'normalized_transaction_id' => $row->id,
                        'matching_result_id' => null,
                        'batch_reference' => $batchReference,
                        'type' => ExceptionType::Duplicate,
                        'status' => ExceptionStatus::Open,
                        'resolution_comment' => $reviewComment,
                    ]);
                    $exceptionsCreated++;
                }
            });

        return new DuplicateScanSummary(count($duplicateGroups), $exceptionsCreated);
    }
}
