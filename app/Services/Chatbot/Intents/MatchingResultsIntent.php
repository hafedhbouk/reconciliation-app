<?php

namespace App\Services\Chatbot\Intents;

use App\Enums\MatchingResultStatus;
use App\Models\MatchingResult;
use App\Models\Source;
use App\Services\Chatbot\Question;
use Illuminate\Database\Eloquent\Builder;

/**
 * Base des questions sur les rapprochements effectués (table matching_results),
 * selon la date du rapprochement (matched_at), en jours de Tunis.
 * Un rapprochement réunit un groupe de lignes des deux sources.
 */
abstract class MatchingResultsIntent extends Intent
{
    public const BASIS = 'Résultats de rapprochement enregistrés (un résultat réunit les lignes rapprochées d’une même clé), selon la date du rapprochement. Rapprochements effectués = statut « rapproché » ou « partiel ».';

    public function permission(): ?string
    {
        return 'matching-results.viewAny';
    }

    public function forbiddenSubject(): string
    {
        return 'les résultats de rapprochement';
    }

    /** Résultats de la période et des sources demandées (règles dont l'une des deux sources est citée). */
    protected function results(Question $question): Builder
    {
        [$from, $to] = self::utcBounds($question);
        $sourceIds = $question->sources === [] ? [] : Source::query()->whereIn('code', $question->sources)->pluck('id')->all();

        return MatchingResult::query()
            ->when($from, fn (Builder $q) => $q->where('matched_at', '>=', $from)->where('matched_at', '<=', $to))
            ->when($sourceIds !== [], fn (Builder $q) => $q->whereHas('matchingRule', fn (Builder $rule) => $rule
                ->whereIn('source_a_id', $sourceIds)->orWhereIn('source_b_id', $sourceIds)));
    }

    /** @return list<string> */
    protected static function effective(): array
    {
        return [MatchingResultStatus::Matched->value, MatchingResultStatus::Partial->value];
    }
}
