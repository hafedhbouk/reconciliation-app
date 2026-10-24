<?php

namespace App\Services\Chatbot\Intents;

use App\Services\Analytics\ReconciliationAnalytics;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;

/**
 * Base des questions sur les lignes importées (taux, lignes sans correspondance,
 * conflits) : chiffres du tableau de bord analytique (ReconciliationAnalytics),
 * selon la date d'opération des lignes.
 */
abstract class LineIntent extends Intent
{
    public const BASIS = 'Lignes des fichiers importés (imports non supprimés), selon leur date d’opération ; statut de rapprochement actuel de chaque ligne.';

    public function __construct(protected ReconciliationAnalytics $analytics) {}

    public function permission(): ?string
    {
        return 'matching-results.viewAny';
    }

    public function forbiddenSubject(): string
    {
        return 'les résultats de rapprochement';
    }

    /** Réponse « aucune ligne » qui rappelle la période réellement couverte par les données. */
    protected function noLines(Question $question, string $status = 'vide', string $consequence = ''): Answer
    {
        $days = $this->analytics->cube()['lignes']->pluck('jour');
        $available = $days->isEmpty() ? ' Aucune ligne n’est importée pour le moment.'
            : ' Les données disponibles vont du '.date('d/m/Y', strtotime($days->min())).' au '.date('d/m/Y', strtotime($days->max())).'.';

        return new Answer($status, 'Aucune ligne importée '.$question->periodText().$question->sourcesText().$consequence.'.'.$available,
            basis: self::BASIS, intent: $this->name());
    }

    /** Par source : [libellé, total, rapprochées, non rapprochées, conflits]. */
    protected function bySource(Question $question): array
    {
        $pivot = $this->analytics->pivot(self::lineFilters($question), 'source', 'statut', 'lignes');
        $rows = [];
        foreach ($pivot['lignes'] as $r => $line) {
            $cells = collect($pivot['colonnes'])->mapWithKeys(fn ($column, $c) => [$column['cle'] => $pivot['cellules'][$r][$c]['valeur']]);
            $rows[$line['libelle']] = [
                'total' => $pivot['totaux_lignes'][$r]['valeur'],
                'rapprochees' => ($cells['matched'] ?? 0) + ($cells['partial'] ?? 0),
                'non_rapprochees' => $cells['unmatched'] ?? 0,
                'conflits' => $cells['conflict'] ?? 0,
            ];
        }

        return $rows;
    }
}
