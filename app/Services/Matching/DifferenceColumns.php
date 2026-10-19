<?php

namespace App\Services\Matching;

/**
 * Colonnes des écarts entre deux fichiers, selon le couple de sources comparé,
 * et texte de la mention « même clé dans l'autre fichier ».
 *
 * Partagé par la page « Écarts par source » (admin/reconciliation/unmatched.blade.php)
 * et l'export des écarts (GenerateMatchingExportJob) : les deux montrent ainsi
 * exactement les mêmes valeurs et les mêmes explications.
 *
 * Colonnes selon le couple :
 *   - ALPHA ↔ BNA : NUM_AUTO, DAT_ENC, MONTANT_ENCAISS / N° autorisation, Date, Montant
 *   - ALPHA ↔ WEB : REFERENCE, NUM_AUTO, DAT_ENC, MONTANT_ENCAISS / reference, recu_paie, date_paiement, montant
 *   - BNA ↔ WEB   : N° autorisation, Date, Montant / recu_paie, date_paiement, montant
 *   - autres couples (avec SMT) : Source, Référence, Montant, Date
 * STEG est un autre nom de la source WEB.
 */
class DifferenceColumns
{
    private bool $isAlphaBna;

    private bool $isAlphaWeb;

    private bool $isBnaWeb;

    /** @param  string  $codeA  code de la source du fichier A (ex. 'ALPHA') ; $codeB : celui du fichier B */
    public function __construct(string $codeA, string $codeB)
    {
        $codes = [strtoupper($codeA), strtoupper($codeB)];
        $hasWeb = in_array('WEB', $codes, true) || in_array('STEG', $codes, true);
        $this->isAlphaBna = in_array('ALPHA', $codes, true) && in_array('BNA', $codes, true);
        $this->isAlphaWeb = in_array('ALPHA', $codes, true) && $hasWeb;
        $this->isBnaWeb = in_array('BNA', $codes, true) && $hasWeb;
    }

    /**
     * Colonnes du tableau d'une des deux sources.
     *
     * @return array<int,array{label:string,field:string}> field = clé de la ligne d'écart à afficher
     */
    public function columns(string $sourceCode): array
    {
        $code = strtoupper($sourceCode);

        if ($this->isAlphaBna) {
            return $code === 'ALPHA'
                ? [
                    ['label' => 'NUM_AUTO', 'field' => 'num_autorisation'],
                    ['label' => 'DAT_ENC', 'field' => 'date'],
                    ['label' => 'MONTANT_ENCAISS', 'field' => 'amount_millimes'],
                ]
                : [
                    ['label' => 'N° autorisation', 'field' => 'num_autorisation'],
                    ['label' => 'Date', 'field' => 'date'],
                    ['label' => 'Montant', 'field' => 'amount_millimes'],
                ];
        }

        if ($this->isAlphaWeb) {
            return $code === 'ALPHA'
                ? [
                    ['label' => 'REFERENCE', 'field' => 'reference'],
                    ['label' => 'NUM_AUTO', 'field' => 'num_autorisation'],
                    ['label' => 'DAT_ENC', 'field' => 'date'],
                    ['label' => 'MONTANT_ENCAISS', 'field' => 'amount_millimes'],
                ]
                : [
                    ['label' => 'reference', 'field' => 'reference'],
                    ['label' => 'recu_paie', 'field' => 'secondary_reference'],
                    ['label' => 'date_paiement', 'field' => 'date'],
                    ['label' => 'montant', 'field' => 'amount_millimes'],
                ];
        }

        if ($this->isBnaWeb) {
            return $code === 'BNA'
                ? [
                    ['label' => 'N° autorisation', 'field' => 'num_autorisation'],
                    ['label' => 'Date', 'field' => 'date'],
                    ['label' => 'Montant', 'field' => 'amount_millimes'],
                ]
                : [
                    ['label' => 'recu_paie', 'field' => 'secondary_reference'],
                    ['label' => 'date_paiement', 'field' => 'date'],
                    ['label' => 'montant', 'field' => 'amount_millimes'],
                ];
        }

        return [
            ['label' => __('Source'), 'field' => 'source'],
            ['label' => __('Référence'), 'field' => 'reference'],
            ['label' => __('Montant'), 'field' => 'amount_millimes'],
            ['label' => __('Date'), 'field' => 'date'],
        ];
    }

    /** Valeur d'une colonne pour une ligne d'écart (tableau stocké dans le cache, voir SnapshotRows). */
    public function value(array $row, string $field): mixed
    {
        return match ($field) {
            'source' => $row['source'] ?? 'N/A',
            'reference' => $row['reference'] ?? ($row['primary_key_value'] ?? ''),
            'num_autorisation' => $row['num_autorisation'] ?? '—',
            'secondary_reference' => $row['secondary_reference'] ?? '—',
            default => $row[$field] ?? '',
        };
    }

    /**
     * Explication d'une ligne exclusive dont la clé existe aussi dans l'autre fichier
     * (données same_key calculées par RuleMatcher::sameKeyNote), ou null s'il n'y en a pas.
     * Les lignes de l'autre fichier sont décrites avec les colonnes de son tableau, ex. :
     *   « aussi dans Alpha : 722057 · 02/07/2026 · 90000 (autre transaction, rapprochée avec une autre ligne BNA) »
     *   « Même ligne présente 2 fois dans Alpha et 1 fois dans BNA : une occurrence reste sans correspondance. »
     *
     * @param  string  $here  nom de la source de la ligne (ex. « BNA »)
     * @param  string  $there  nom de l'autre source (ex. « Alpha »)
     * @param  string  $thereCode  code de l'autre source : choisit les colonnes décrivant ses lignes
     */
    public function sameKeyNote(?array $sameKey, string $here, string $there, string $thereCode): ?string
    {
        if (empty($sameKey)) {
            return null;
        }

        if ($duplicate = $sameKey['duplicate'] ?? null) {
            $extra = $duplicate['here'] - $duplicate['there'];

            return __('Même ligne présente :here fois dans :a et :there fois dans :b : ', [
                'here' => $duplicate['here'], 'a' => $here, 'there' => $duplicate['there'], 'b' => $there,
            ]).($extra > 1
                ? __(':count occurrences restent sans correspondance.', ['count' => $extra])
                : __('une occurrence reste sans correspondance.'));
        }

        $columns = $this->columns($thereCode);
        $rows = collect($sameKey['rows'] ?? [])->map(fn (array $row) => collect($columns)
            ->map(fn (array $column) => $this->value($row, $column['field']))->implode(' · '));
        $count = (int) ($sameKey['count'] ?? $rows->count());
        if ($count > $rows->count()) {
            $rows->push(__('et :count de plus', ['count' => $count - $rows->count()]));
        }

        return __('aussi dans :source : ', ['source' => $there]).$rows->implode(' ; ').' ('.($count > 1
            ? __('autres transactions, rapprochées avec d’autres lignes :source', ['source' => $here])
            : __('autre transaction, rapprochée avec une autre ligne :source', ['source' => $here])).')';
    }
}
