<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;
use Illuminate\Support\Carbon;

/** « Quelle est l'évolution du nombre de rapprochements par mois ? » */
class MonthlyTrendIntent extends MatchingResultsIntent
{
    /** Au-delà, seuls les derniers mois sont détaillés. */
    private const MAX_MONTHS = 24;

    public function name(): string
    {
        return 'evolution_mensuelle';
    }

    public function example(): string
    {
        return 'Quelle est l’évolution du nombre de rapprochements par mois ?';
    }

    public function score(Question $question): int
    {
        return $question->mentions('evolution', 'par mois', 'mensuel', 'chaque mois', 'tendance', 'mois par mois') ? 9 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        $timezone = (string) config('app.display_timezone');
        $first = $this->results($question)->whereIn('status', self::effective())->min('matched_at');
        $last = $this->results($question)->whereIn('status', self::effective())->max('matched_at');
        if ($first === null) {
            return new Answer('vide', 'Aucun rapprochement n’a été effectué '.$question->periodText().$question->sourcesText().' : pas d’évolution à montrer.',
                basis: self::BASIS, intent: $this->name());
        }

        // Un comptage par mois (heure de Tunis), du premier au dernier mois qui contient des rapprochements.
        $start = Carbon::parse($first, 'UTC')->setTimezone($timezone)->startOfMonth();
        $end = Carbon::parse($last, 'UTC')->setTimezone($timezone)->startOfMonth();
        $months = [];
        for ($month = $end->copy(); $month->gte($start) && count($months) < self::MAX_MONTHS; $month->subMonthNoOverflow()) {
            $months[] = $month->copy();
        }
        $months = array_reverse($months);
        $rows = [];
        $counts = [];
        foreach ($months as $month) {
            $count = $this->results($question)->whereIn('status', self::effective())
                ->where('matched_at', '>=', $month->copy()->utc())->where('matched_at', '<', $month->copy()->addMonthNoOverflow()->utc())->count();
            $counts[] = $count;
            $rows[] = [self::sentence($month->translatedFormat('F Y')), self::number($count)];
        }

        $text = 'Rapprochements effectués par mois'.$question->sourcesText().' ('.$question->periodText().') : ';
        if (count($counts) === 1) {
            $text .= 'tous ont été faits en '.$months[0]->translatedFormat('F Y').' ('.self::number($counts[0]).'). Il n’y a pas encore d’évolution à comparer sur plusieurs mois.';
        } else {
            $change = $counts[0] > 0 ? ($counts[array_key_last($counts)] - $counts[0]) * 100 / $counts[0] : null;
            $text .= self::number(array_sum($counts)).' sur '.count($counts).' mois'
                .($change === null ? '.' : ', '.($change >= 0 ? 'en hausse' : 'en baisse').' de '.self::percent(round(abs($change), 1))
                    .' entre '.$months[0]->translatedFormat('F Y').' et '.$months[array_key_last($months)]->translatedFormat('F Y').'.');
        }
        $start->lt($months[0]) && $text .= ' Seuls les '.self::MAX_MONTHS.' derniers mois sont détaillés.';

        return new Answer('ok', $text, $rows, self::BASIS, intent: $this->name());
    }
}
