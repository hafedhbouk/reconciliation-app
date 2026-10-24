<?php

namespace App\Services\Chatbot\Intents;

use App\Enums\MatchingResultStatus;
use App\Models\User;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;

/** « Combien de rapprochements ont été effectués cette année ? » */
class MatchingCountIntent extends MatchingResultsIntent
{
    public function name(): string
    {
        return 'rapprochements_effectues';
    }

    public function example(): string
    {
        return 'Combien de rapprochements ont été effectués cette année ?';
    }

    public function score(Question $question): int
    {
        return $question->mentions('rapprochement', '\bmatching', '\brapproche') && $question->mentions('combien', '\bnombre\b', '\btotal\b', 'quantite')
            ? 6 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        $byStatus = $this->results($question)->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $done = $byStatus->only(self::effective())->sum();
        if ($byStatus->sum() === 0) {
            return new Answer('vide', 'Aucun rapprochement n’a été effectué '.$question->periodText().$question->sourcesText().'.',
                basis: self::BASIS, intent: $this->name());
        }
        $lots = $this->results($question)->distinct()->count('batch_reference');
        $rows = collect(MatchingResultStatus::cases())
            ->filter(fn (MatchingResultStatus $status) => ($byStatus[$status->value] ?? 0) > 0)
            ->map(fn (MatchingResultStatus $status) => [$status->label(), self::number($byStatus[$status->value])])->values()->all();
        $rows[] = ['Exécutions (lots)', self::number($lots)];

        return new Answer('ok',
            self::sentence($question->periodText()).$question->sourcesText().', '
                .self::count($done, 'rapprochement a été effectué', 'rapprochements ont été effectués')
                .', en '.self::count($lots, 'exécution', 'exécutions').'.',
            $rows, self::BASIS, intent: $this->name());
    }
}
