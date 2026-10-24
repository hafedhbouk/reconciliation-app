<?php

namespace App\Services\Chatbot;

/**
 * Réponse du chatbot, envoyée au navigateur en JSON.
 *
 * Statuts :
 *   ok                 : chiffres calculés sur les données réelles ;
 *   vide               : la question est comprise mais aucune donnée ne correspond (on le dit, sans inventer) ;
 *   non_calculable     : la valeur ne peut pas être calculée (ex. taux sans aucune ligne) ;
 *   interdit           : l'utilisateur n'a pas le droit de consulter ces données ;
 *   non_pris_en_charge : question non reconnue ;
 *   erreur             : incident technique pendant le calcul.
 */
readonly class Answer
{
    /**
     * @param  list<array{0:string,1:string}>  $rows  détail « libellé → valeur »
     * @param  list<string>  $suggestions  questions proposées ensuite
     */
    public function __construct(
        public string $status,
        public string $text,
        public array $rows = [],
        public ?string $basis = null, // comment le chiffre est calculé (données utilisées)
        public array $suggestions = [],
        public ?string $intent = null,
    ) {}

    public function toArray(): array
    {
        return [
            'statut' => $this->status,
            'texte' => $this->text,
            'details' => array_map(fn (array $row) => ['libelle' => $row[0], 'valeur' => $row[1]], $this->rows),
            'calcul' => $this->basis,
            'suggestions' => $this->suggestions,
            'intention' => $this->intent,
        ];
    }
}
