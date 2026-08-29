<?php

namespace App\Services\Import;

/**
 * Construit les lignes Transaction et NormalizedTransaction prêtes pour
 * l'insertion en masse.
 *
 * S'interface avec MappingEngine pour recevoir les valeurs transformées,
 * puis assemble : la ligne transactions, le snapshot normalized (référence,
 * montant, date, hash) et la ligne normalized_transactions. Le cache
 * interne des devises évite un lookup par ligne sur les imports volumineux.
 */
use App\Enums\MappingTargetField;
use App\Enums\MatchingStatus;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Source;
use Carbon\Carbon;

class TransactionNormalizer
{
    /** @var array<string,int|null> in-memory cache so an 80k-row import doesn't run one currency lookup per row */
    private array $currencyIdCache = [];

    /**
     * import_row_id is deliberately omitted here — the caller (ProcessImportJob)
     * only learns the real import_rows.id after its own bulk insert + a
     * correlation query, and sets it on the returned array afterward.
     *
     * @param array<string,mixed> $transformed keyed by MappingTargetField value
     * @return array<string,mixed> ready for a transactions bulk insert() row (minus import_row_id)
     */
    public function buildTransactionRow(array $transformed, Source $source, Import $import, int $userId): array
    {
        // Construire la date à partir de datetime si la date seule n'est pas fournie.
        $date = $transformed[MappingTargetField::Date->value]
            ?? $this->dateFromDatetime($transformed[MappingTargetField::Datetime->value] ?? null);

        $now = now();

        return [
            'import_id' => $import->id,
            'source_id' => $source->id,
            'bank_id' => $source->bank_id,
            // Résoudre la devise : code explicite > devise par défaut de la source.
            'currency_id' => $this->resolveCurrencyId($transformed[MappingTargetField::CurrencyCode->value] ?? null, $source),
            'external_reference' => $transformed[MappingTargetField::Reference->value] ?? null,
            'transaction_date' => $date,
            'transaction_datetime' => $transformed[MappingTargetField::Datetime->value] ?? null,
            'amount_millimes' => $transformed[MappingTargetField::Amount->value] ?? null,
            'canal' => $transformed[MappingTargetField::Canal->value] ?? null,
            // Conserver le payload complet transformé pour debug et matching avancé.
            'raw_payload' => json_encode($transformed),
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    /**
     * The normalized snapshot's content (reference/amount/date/dedup_hash) is
     * fully derivable from the transaction row alone — it does NOT need a
     * real transaction_id. Splitting this out lets ProcessImportJob compute
     * it in the same pass as buildTransactionRow(), before any bulk insert
     * has happened, so import_rows can be written once with complete data
     * instead of inserted-then-updated.
     *
     * @param array<string,mixed> $transactionRow the row built by buildTransactionRow()
     * @return array<string,mixed> {normalized_reference, normalized_amount_millimes, normalized_date, dedup_hash, matching_status}
     */
    public function computeNormalizedSnapshot(array $transactionRow): array
    {
        // SMT n'a pas de colonne référence (spécification client : date + montant).
        // Pour ces sources, on construit une clé composite date|montant afin que
        // normalized_reference ne soit jamais null et que le moteur de matching
        // puisse grouper dessus.
        $reference = $transactionRow['external_reference']
            ?? ($transactionRow['transaction_date'].'|'.$transactionRow['amount_millimes']);

        // Hash déterministe pour le dédoublonnage : identique pour 2 lignes
        // ayant la même source, référence, montant et date.
        $hash = hash('sha256', implode('|', [
            $transactionRow['source_id'],
            $reference,
            $transactionRow['amount_millimes'],
            $transactionRow['transaction_date'],
        ]));

        return [
            'normalized_reference' => $reference,
            'normalized_amount_millimes' => $transactionRow['amount_millimes'],
            'normalized_date' => $transactionRow['transaction_date'],
            'dedup_hash' => $hash,
            'matching_status' => MatchingStatus::Unmatched->value,
        ];
    }

    /**
     * @param array<string,mixed> $snapshot the array built by computeNormalizedSnapshot()
     * @return array<string,mixed> ready for a normalized_transactions bulk insert() row
     */
    public function buildNormalizedRow(int $transactionId, array $snapshot, int $userId): array
    {
        $now = now();

        return $snapshot + [
            'transaction_id' => $transactionId,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    private function dateFromDatetime(?string $datetime): ?string
    {
        return $datetime === null ? null : Carbon::parse($datetime)->toDateString();
    }

    private function resolveCurrencyId(?string $currencyCode, Source $source): ?int
    {
        if ($currencyCode === null) {
            return $source->default_currency_id;
        }

        if (! array_key_exists($currencyCode, $this->currencyIdCache)) {
            $this->currencyIdCache[$currencyCode] = Currency::query()->where('iso_code', $currencyCode)->value('id');
        }

        return $this->currencyIdCache[$currencyCode] ?? $source->default_currency_id;
    }
}
