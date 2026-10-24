<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Analytics\ReconciliationAnalytics;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;

/** « Combien de lignes n'ont pas trouvé de correspondance ? » */
class UnmatchedLinesIntent extends LineIntent
{
    public function name(): string
    {
        return 'lignes_sans_correspondance';
    }

    public function example(): string
    {
        return 'Combien de lignes n’ont pas trouvé de correspondance ?';
    }

    public function score(Question $question): int
    {
        return $question->mentions('pas (encore )?(trouve|eu|de) (de |une )?correspondance', 'sans correspondance', 'aucune correspondance',
            'non rapproche', 'pas (ete |encore )?rapproche', 'non apparie', 'orphelin', '\bmanquant') ? 9 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        $kpis = $this->analytics->kpis(self::lineFilters($question));
        if ($kpis['lignes'] === 0) {
            return $this->noLines($question);
        }
        $rows = [];
        foreach ($this->bySource($question) as $source => $counts) {
            $rows[] = [$source, self::number($counts['non_rapprochees']).' sur '.self::number($counts['total'])];
        }
        $count = $kpis['non_rapprochees'];

        return new Answer('ok',
            self::sentence($question->periodText()).$question->sourcesText().', '.self::number($count)
                .($count > 1 ? ' lignes n’ont' : ' ligne n’a').' pas trouvé de correspondance, sur '.self::count($kpis['lignes'], 'ligne importée', 'lignes importées')
                .' ('.self::percent(ReconciliationAnalytics::percent($count, $kpis['lignes'])).').'
                .($kpis['conflits'] > 0 ? ' En plus, '.self::count($kpis['conflits'], 'ligne est', 'lignes sont').' en conflit (même clé, montant ou date différent).' : ''),
            $rows, self::BASIS.' Sans correspondance = statut « non rapproché ».', intent: $this->name());
    }
}
