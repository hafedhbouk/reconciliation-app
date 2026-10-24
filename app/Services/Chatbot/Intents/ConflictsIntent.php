<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;

/** « Combien de conflits en juillet ? » */
class ConflictsIntent extends LineIntent
{
    public function name(): string
    {
        return 'conflits';
    }

    public function example(): string
    {
        return 'Combien de conflits pour BNA en juillet 2026 ?';
    }

    public function score(Question $question): int
    {
        return $question->mentions('\bconflit', '\banomalie', 'incoheren', 'ecart de montant', 'ecart de date')
            && ! $question->mentions('\bexception') ? 7 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        $kpis = $this->analytics->kpis(self::lineFilters($question));
        if ($kpis['lignes'] === 0) {
            return $this->noLines($question);
        }
        $rows = [];
        foreach ($this->bySource($question) as $source => $counts) {
            $rows[] = [$source, self::number($counts['conflits'])];
        }

        return new Answer('ok',
            self::sentence($question->periodText()).$question->sourcesText().', '.self::number($kpis['conflits'])
                .($kpis['conflits'] > 1 ? ' lignes sont' : ' ligne est').' en conflit sur '.self::count($kpis['lignes'], 'ligne importée', 'lignes importées')
                .' (taux d’anomalie : '.self::percent($kpis['taux_anomalie']).').',
            $rows, self::BASIS.' Conflit = même clé de rapprochement mais montant ou date différent.', intent: $this->name());
    }
}
