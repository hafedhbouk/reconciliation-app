<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Analytics\ReconciliationAnalytics;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;

/** « Quel est le taux de rapprochement du mois dernier ? » */
class MatchingRateIntent extends LineIntent
{
    public function name(): string
    {
        return 'taux_rapprochement';
    }

    public function example(): string
    {
        return 'Quel est le taux de rapprochement du mois dernier ?';
    }

    public function score(Question $question): int
    {
        return $question->mentions('\btaux\b', 'pourcentage', '\bratio\b', '\bproportion\b')
            && ! $question->mentions('\bechec', '\bechou', 'succes', '\breussite\b') ? 8 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        $kpis = $this->analytics->kpis(self::lineFilters($question));
        if ($kpis['lignes'] === 0) {
            return $this->noLines($question, 'non_calculable', ' : le taux de rapprochement ne peut pas être calculé');
        }
        $rows = [];
        foreach ($this->bySource($question) as $source => $counts) {
            $rows[] = [$source, self::percent(ReconciliationAnalytics::percent($counts['rapprochees'], $counts['total']))
                .' ('.self::number($counts['rapprochees']).' / '.self::number($counts['total']).')'];
        }

        return new Answer('ok',
            self::sentence($question->periodText()).$question->sourcesText().', le taux de rapprochement est de '
                .self::percent($kpis['taux_rapprochement']).' : '.self::count($kpis['rapprochees'], 'ligne rapprochée', 'lignes rapprochées').' sur '
                .self::number($kpis['lignes']).'.',
            $rows, self::BASIS.' Rapprochées = statut « rapproché » ou « partiel ».', intent: $this->name());
    }
}
