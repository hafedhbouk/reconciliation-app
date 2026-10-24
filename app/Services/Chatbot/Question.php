<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Carbon;

/**
 * Question de l'utilisateur, analysée par QuestionParser.
 *
 * $text   : texte en minuscules, sans accents ni ponctuation (pour reconnaître les mots-clés) ;
 * $from/$to : période demandée (jours calendaires inclus, heure de Tunis), null = toutes les données ;
 * $periodLabel : la période en clair, reprise dans la réponse (« en juillet 2026 ») ;
 * $sources : codes des sources citées (ALPHA, BNA…), vide = toutes.
 */
readonly class Question
{
    /** @param list<string> $sources */
    public function __construct(
        public string $original,
        public string $text,
        public ?Carbon $from = null,
        public ?Carbon $to = null,
        public ?string $periodLabel = null,
        public array $sources = [],
    ) {}

    /** Vrai si le texte contient l'une des expressions régulières (appliquées au texte normalisé). */
    public function mentions(string ...$patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('/'.$pattern.'/u', $this->text) === 1) {
                return true;
            }
        }

        return false;
    }

    public function hasPeriod(): bool
    {
        return $this->from !== null;
    }

    /** « en juillet 2026 », ou « sur l'ensemble des données » sans période. */
    public function periodText(): string
    {
        return $this->periodLabel ?? 'sur l’ensemble des données';
    }

    /** « pour ALPHA et BNA », ou chaîne vide pour toutes les sources. */
    public function sourcesText(): string
    {
        if ($this->sources === []) {
            return '';
        }
        $sources = $this->sources;
        $last = array_pop($sources);

        return ' pour '.($sources === [] ? $last : implode(', ', $sources).' et '.$last);
    }
}
