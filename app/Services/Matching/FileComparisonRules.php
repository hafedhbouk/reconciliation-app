<?php

namespace App\Services\Matching;

use App\Models\Source;
use InvalidArgumentException;

/**
 * Shared field definitions for matching and displaying differences between files.
 *
 * Quand l'utilisateur compare DEUX FICHIERS précis (écran « Comparer deux
 * fichiers »), on n'utilise pas les règles en base : ce service fabrique
 * des critères sur mesure, selon le couple de sources choisi.
 *
 * Clé de regroupement (primary_key) selon le couple :
 *   - avec SMT       : date|montant (SMT n'a aucun identifiant)
 *   - ALPHA ↔ WEB    : la référence, puis vérification NUM_AUTO ↔ recu_paie
 *   - ALPHA ↔ BNA    : le N° autorisation des deux côtés
 *   - WEB ↔ BNA      : recu_paie ↔ N° autorisation
 * STEG est un autre nom de la source WEB, traité de la même façon.
 *
 * Le tableau renvoyé a la même forme que MatchingRule::criteria
 * (voir app/Models/MatchingRule.php).
 */
class FileComparisonRules
{
    public function criteria(Source $a, Source $b): array
    {
        // Codes des deux sources en majuscules, STEG étant converti en WEB.
        $codes = array_map(fn ($source) => strtoupper($source->code) === 'STEG' ? 'WEB' : strtoupper($source->code), [$a, $b]);
        // Refuse la même source deux fois, ou une source inconnue.
        if ($codes[0] === $codes[1] || array_diff($codes, ['ALPHA', 'BNA', 'WEB', 'SMT']) !== []) {
            throw new InvalidArgumentException('Combinaison de sources non prise en charge pour la comparaison de fichiers.');
        }

        // Champs toujours comparés après le regroupement.
        $verify = ['amount', 'date'];
        // Champs dont la valeur doit être purement numérique pour participer (sinon la ligne est mise de côté).
        $excludedNonNumeric = ['a' => [], 'b' => []];
        if (in_array('SMT', $codes, true)) {
            $primary = ['a' => 'date|amount', 'b' => 'date|amount'];
        } elseif (in_array('ALPHA', $codes, true) && in_array('WEB', $codes, true)) {
            $primary = ['a' => 'reference', 'b' => 'reference'];
            // Vérification croisée : le NUM_AUTO d'ALPHA doit égaler le recu_paie de WEB.
            $verify[] = [
                'a' => $codes[0] === 'ALPHA' ? 'num_autorisation' : 'secondary_reference',
                'b' => $codes[1] === 'ALPHA' ? 'num_autorisation' : 'secondary_reference',
            ];
            $excludedNonNumeric[$codes[0] === 'ALPHA' ? 'b' : 'a'] = ['secondary_reference'];
        } else {
            // BNA has no equivalent of ALPHA's REFERENCE.
            // (Couples restants : ALPHA ↔ BNA et WEB ↔ BNA, regroupés sur l'autorisation.)
            $primary = [
                'a' => $codes[0] === 'WEB' ? 'secondary_reference' : 'num_autorisation',
                'b' => $codes[1] === 'WEB' ? 'secondary_reference' : 'num_autorisation',
            ];
        }
        // Pour ALPHA ↔ BNA, une autorisation non numérique (ex. invalide) ne peut pas être rapprochée.
        if (in_array('ALPHA', $codes, true) && in_array('BNA', $codes, true)) {
            $excludedNonNumeric['a'] = ['num_autorisation'];
            $excludedNonNumeric['b'] = ['num_autorisation'];
        }

        return [
            'file_comparison' => true, // indique à RuleMatcher qu'il s'agit d'une comparaison de deux fichiers
            'tolerance_amount_millimes' => 0,
            'tolerance_days' => 0,
            'excluded_status_raw' => ['a' => [], 'b' => []],
            'primary_key' => $primary,
            'verify_fields' => $verify,
            'excluded_non_numeric' => $excludedNonNumeric,
        ];
    }
}
