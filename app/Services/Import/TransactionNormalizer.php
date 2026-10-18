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
 *
 * Place dans le pipeline (appelé par ProcessImportJob pour chaque ligne) :
 *   ligne brute --MappingEngine--> valeurs transformées
 *     --buildTransactionRow()------> ligne de la table transactions
 *     --computeNormalizedSnapshot()-> ligne de la table normalized_transactions
 *
 * Les méthodes renvoient de simples tableaux (pas des modèles Eloquent) :
 * ProcessImportJob les insère ensuite 500 par 500 en une seule requête SQL,
 * ce qui est beaucoup plus rapide que 80 000 appels à ->save().
 */
use App\Enums\MappingTargetField;
use App\Enums\MatchingStatus;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Source;
use Carbon\Carbon;

class TransactionNormalizer
{
    /**
     * Mémoire des devises déjà cherchées : code ISO => id.
     *
     * @var array<string,int|null> in-memory cache so an 80k-row import doesn't run one currency lookup per row
     */
    private array $currencyIdCache = [];

    /**
     * Assemble la ligne de la table transactions.
     *
     * import_row_id is deliberately omitted here — the caller (ProcessImportJob)
     * only learns the real import_rows.id after its own bulk insert + a
     * correlation query, and sets it on the returned array afterward.
     *
     * @param  array<string,mixed>  $transformed  keyed by MappingTargetField value
     * @return array<string,mixed> ready for a transactions bulk insert() row (minus import_row_id)
     */
    public function buildTransactionRow(array $transformed, Source $source, Import $import, ?int $userId): array
    {
        // Construire la date à partir de datetime si la date seule n'est pas fournie.
        // (MappingTargetField::Date->value vaut 'date' : on lit $transformed['date'].)
        $date = $transformed[MappingTargetField::Date->value]
            ?? $this->dateFromDatetime($transformed[MappingTargetField::Datetime->value] ?? null);

        $now = now(); // date et heure actuelles (helper Laravel)

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
            // Avec une insertion en masse, Laravel ne remplit pas ces colonnes tout seul : on le fait ici.
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    /**
     * Calcule les champs utilisés par le rapprochement (la « Données normalisées »
     * affichée sur la page d'un import).
     *
     * The normalized snapshot's content (reference/amount/date/dedup_hash) is
     * fully derivable from the transaction row alone — it does NOT need a
     * real transaction_id. Splitting this out lets ProcessImportJob compute
     * it in the same pass as buildTransactionRow(), before any bulk insert
     * has happened, so import_rows can be written once with complete data
     * instead of inserted-then-updated.
     *
     * Résultat selon la source :
     *   ALPHA : reference = REFERENCE (185113460), secondary = NUM_AUTO (697464)
     *   WEB   : reference = reference,             secondary = recu_paie
     *   BNA   : reference = N° autorisation (504190), pas de secondary
     *   SMT   : reference = "2026-05-15|253000" (date|montant), pas de secondary
     *
     * @param  array<string,mixed>  $transactionRow  the row built by buildTransactionRow()
     * @return array<string,mixed> {normalized_reference, normalized_secondary_reference, normalized_amount_millimes, normalized_date, dedup_hash, matching_status}
     */
    public function computeNormalizedSnapshot(array $transactionRow): array
    {
        // raw_payload peut arriver en texte JSON (depuis buildTransactionRow) : on le décode en tableau.
        $payload = $transactionRow['raw_payload'] ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        }
        // L'« autorisation » : num_autorisation (ALPHA, BNA) ou, à défaut, secondary_reference (recu_paie de WEB).
        $authorization = $payload['num_autorisation'] ?? $payload['secondary_reference'] ?? null;
        // Clé de secours pour les sources sans aucun identifiant (SMT) : "date|montant".
        $dateAmountReference = $transactionRow['transaction_date'].'|'.$transactionRow['amount_millimes'];
        $externalReference = $transactionRow['external_reference'] ?? null;
        // normalized_reference : external_reference (ALPHA, WEB) > N° autorisation
        // (BNA, aucune colonne référence) > date|montant (SMT).
        $reference = $externalReference ?? $authorization ?? $dateAmountReference;
        // normalized_secondary_reference : identifiant distinct de la référence
        // principale (NUM_AUTO pour ALPHA, recu_paie pour WEB). Null pour BNA,
        // dont l'autorisation est déjà dans normalized_reference.
        $secondaryReference = ($externalReference !== null && $authorization !== null
            && (string) $authorization !== (string) $externalReference) ? (string) $authorization : null;
        // Keep the existing dedup hash stable for legacy BNA rows, whose
        // normalized reference was date|amount before it became the authorization.
        $dedupReference = $externalReference ?? $dateAmountReference;
        // Include the receipt/authorization: same date and amount alone do not
        // identify a BNA payment. Keep the old format for reference-only sources.
        // (dedup_hash = empreinte SHA-256 de la transaction. Deux lignes avec la
        // même empreinte dans une même source sont des doublons, voir DuplicateDetector.)
        $hash = hash('sha256', implode('|', [
            $transactionRow['source_id'],
            $dedupReference,
            $transactionRow['amount_millimes'],
            $transactionRow['transaction_date'],
            // `...` (« spread ») insère le contenu du tableau : un élément de plus, ou rien.
            ...($authorization !== null && $externalReference === null
                ? ['authorization:'.(string) $authorization] : []),
        ]));

        return [
            'normalized_reference' => $reference,
            'normalized_secondary_reference' => $secondaryReference,
            'normalized_amount_millimes' => $transactionRow['amount_millimes'],
            'normalized_date' => $transactionRow['transaction_date'],
            'dedup_hash' => $hash,
            // Toute transaction importée commence « non rapprochée ».
            'matching_status' => MatchingStatus::Unmatched->value,
        ];
    }

    /**
     * Snapshot as stored in import_rows.normalized_data (shown on the import
     * page): an absent secondary reference is omitted rather than shown as null.
     * (On n'affiche donc pas « normalized_secondary_reference: null » pour BNA et SMT.)
     *
     * @param  array<string,mixed>  $snapshot  the array built by computeNormalizedSnapshot()
     */
    public function importRowNormalizedData(array $snapshot): array
    {
        if (($snapshot['normalized_secondary_reference'] ?? null) === null) {
            unset($snapshot['normalized_secondary_reference']);
        }

        return $snapshot;
    }

    /**
     * Complète le snapshot avec l'id de la transaction et les colonnes
     * techniques, pour l'insérer dans normalized_transactions.
     *
     * @param  array<string,mixed>  $snapshot  the array built by computeNormalizedSnapshot()
     * @return array<string,mixed> ready for a normalized_transactions bulk insert() row
     */
    public function buildNormalizedRow(int $transactionId, array $snapshot, ?int $userId): array
    {
        $now = now();

        // L'opérateur `+` fusionne deux tableaux (les clés du premier sont prioritaires).
        return $snapshot + [
            'transaction_id' => $transactionId,
            'created_at' => $now,
            'updated_at' => $now,
            'created_by' => $userId,
            'updated_by' => $userId,
        ];
    }

    /** Extrait la date seule d'une date-heure : '2026-05-01 00:02:29' -> '2026-05-01'. */
    private function dateFromDatetime(?string $datetime): ?string
    {
        return $datetime === null ? null : Carbon::parse($datetime)->toDateString();
    }

    /**
     * Trouve l'id de la devise à partir de son code ISO (ex. 'TND').
     * Sans code, ou avec un code inconnu, on prend la devise par défaut de la source.
     */
    private function resolveCurrencyId(?string $currencyCode, Source $source): ?int
    {
        if ($currencyCode === null) {
            return $source->default_currency_id;
        }

        // Une seule requête SQL par code devise pour tout l'import, grâce au cache.
        if (! array_key_exists($currencyCode, $this->currencyIdCache)) {
            $this->currencyIdCache[$currencyCode] = Currency::query()->where('iso_code', $currencyCode)->value('id');
        }

        return $this->currencyIdCache[$currencyCode] ?? $source->default_currency_id;
    }
}
