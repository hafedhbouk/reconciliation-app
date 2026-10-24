<?php

namespace App\Services\Chatbot\Intents;

use App\Models\User;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;
use Illuminate\Support\Carbon;

/**
 * Une question métier prise en charge par le chatbot.
 *
 * Chaque intention :
 *   - reconnaît les questions qui la concernent (score() > 0, le plus haut gagne) ;
 *   - déclare la permission nécessaire (vérifiée par Chatbot avant answer()) ;
 *   - calcule la réponse avec un service métier existant, jamais avec du SQL
 *     construit à partir de la question.
 */
abstract class Intent
{
    /** Identifiant court (journal, tests). */
    abstract public function name(): string;

    /** Exemple de question, proposé dans l'aide. */
    abstract public function example(): string;

    /** 0 = question non concernée ; plus le score est haut, plus l'intention est probable. */
    abstract public function score(Question $question): int;

    abstract public function answer(Question $question, User $user): Answer;

    /** Permission nécessaire, null = toute personne connectée (données limitées à ses droits). */
    public function permission(): ?string
    {
        return null;
    }

    /** Libellé des données refusées, pour le message « accès refusé ». */
    public function forbiddenSubject(): string
    {
        return 'ces données';
    }

    protected static function number(int|float|null $value): string
    {
        return $value === null ? '—' : number_format((float) $value, 0, ',', ' ');
    }

    /** « 1 ligne », « 2 lignes » : nombre suivi du nom accordé. */
    protected static function count(int $value, string $one, string $many): string
    {
        return self::number($value).' '.($value > 1 ? $many : $one);
    }

    protected static function percent(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, $value > 0 && $value < 1 ? 3 : 1, ',', ' ').' %';
    }

    /** Bornes UTC d'une période en jours de Tunis (pour les dates d'événements : created_at, matched_at). */
    protected static function utcBounds(Question $question): array
    {
        return [$question->from?->copy()->startOfDay()->utc(), $question->to?->copy()->endOfDay()->utc()];
    }

    /** Filtres pour ReconciliationAnalytics (date d'opération, sources). */
    protected static function lineFilters(Question $question): array
    {
        return array_filter([
            'date_debut' => $question->from?->format('Y-m-d'),
            'date_fin' => $question->to?->format('Y-m-d'),
            'sources' => $question->sources,
        ]);
    }

    /** Majuscule initiale (« Le mois dernier… »). */
    protected static function sentence(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    protected static function date(?Carbon $date): string
    {
        return $date?->format('d/m/Y') ?? '—';
    }
}
