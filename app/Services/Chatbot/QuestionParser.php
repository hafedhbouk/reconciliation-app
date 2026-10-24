<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Analyse une question en français : texte normalisé, période et sources citées.
 *
 * Périodes reconnues (jours calendaires, heure de Tunis) :
 *   aujourd'hui, hier, cette semaine, la semaine dernière, ce mois-ci, le mois
 *   dernier, cette année, l'année dernière, « en 2026 », « en juillet » ou
 *   « juillet 2026 », « le 15/07/2026 », « du 01/07/2026 au 15/07/2026 ».
 * Une question sans période porte sur toutes les données.
 * Aucun appel à un service externe : tout est fait ici, sur le serveur.
 */
class QuestionParser
{
    private const MONTHS = ['janvier' => 1, 'fevrier' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7,
        'aout' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'decembre' => 12];

    /** @param list<string> $sourceCodes codes des sources existantes */
    public function parse(string $question, array $sourceCodes = []): Question
    {
        // Dates (01/07/2026) lues avant la normalisation, qui retire la ponctuation.
        $dates = [];
        preg_match_all('#\b(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})\b#', $question, $found, PREG_SET_ORDER);
        foreach ($found as [, $day, $month, $year]) {
            if (checkdate((int) $month, (int) $day, (int) $year)) {
                $dates[] = Carbon::create((int) $year, (int) $month, (int) $day, 0, 0, 0, $this->timezone());
            }
        }
        $text = $this->normalize($question);
        [$from, $to, $label] = $this->period($text, $dates) + [null, null, null];

        $sources = array_values(array_filter($sourceCodes,
            fn (string $code) => preg_match('/\b'.preg_quote(Str::lower(Str::ascii($code)), '/').'\b/u', $text) === 1));

        return new Question($question, $text, $from, $to, $label, $sources);
    }

    /** Minuscules, sans accents, apostrophes et ponctuation remplacées par des espaces. */
    public function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9]+/', ' ', $text)));
    }

    /** @param list<Carbon> $dates @return array{0?:Carbon,1?:Carbon,2?:string} */
    private function period(string $text, array $dates): array
    {
        $today = Carbon::now($this->timezone())->startOfDay();
        $format = fn (Carbon $date) => $date->format('d/m/Y');
        $month = fn (Carbon $start) => [$start->copy()->startOfMonth(), $start->copy()->endOfMonth()->startOfDay(), $start->translatedFormat('F Y')];

        if (count($dates) >= 2) {
            [$start, $end] = $dates[0]->lte($dates[1]) ? [$dates[0], $dates[1]] : [$dates[1], $dates[0]];

            return [$start, $end, 'du '.$format($start).' au '.$format($end)];
        }
        if (count($dates) === 1) {
            return [$dates[0], $dates[0], 'le '.$format($dates[0])];
        }
        if (preg_match('/\baujourd hui\b/', $text)) {
            return [$today, $today, 'aujourd’hui ('.$format($today).')'];
        }
        if (preg_match('/\bhier\b/', $text)) {
            $day = $today->copy()->subDay();

            return [$day, $day, 'hier ('.$format($day).')'];
        }
        if (preg_match('/\b(la )?semaine (derniere|precedente|passee)\b/', $text)) {
            $start = $today->copy()->subWeek()->startOfWeek();

            return [$start, $start->copy()->endOfWeek()->startOfDay(), 'la semaine dernière (du '.$format($start).' au '.$format($start->copy()->endOfWeek()).')'];
        }
        if (preg_match('/\bcette semaine\b/', $text)) {
            return [$today->copy()->startOfWeek(), $today, 'cette semaine (depuis le '.$format($today->copy()->startOfWeek()).')'];
        }
        if (preg_match('/\b(le )?mois (dernier|precedent|passe)\b/', $text)) {
            [$start, $end, $name] = $month($today->copy()->subMonthNoOverflow());

            return [$start, $end, "le mois dernier ({$name})"];
        }
        if (preg_match('/\bce mois( ci)?\b|\bmois en cours\b/', $text)) {
            [$start, $end, $name] = $month($today);

            return [$start, $end, "ce mois-ci ({$name})"];
        }
        if (preg_match('/\b(l )?annee (derniere|precedente|passee)\b/', $text)) {
            $year = $today->year - 1;

            return [$today->copy()->setDate($year, 1, 1), $today->copy()->setDate($year, 12, 31), "l’année dernière ({$year})"];
        }
        if (preg_match('/\bcette annee\b|\bannee en cours\b/', $text)) {
            return [$today->copy()->startOfYear(), $today->copy()->endOfYear()->startOfDay(), "cette année ({$today->year})"];
        }
        if (preg_match('/\b('.implode('|', array_keys(self::MONTHS)).')(?: (20\d{2}))?\b/', $text, $match)) {
            $number = self::MONTHS[$match[1]];
            // Sans année : le mois le plus récent qui n'est pas dans le futur.
            $year = isset($match[2]) ? (int) $match[2] : ($number <= $today->month ? $today->year : $today->year - 1);

            [$start, $end, $name] = $month(Carbon::create($year, $number, 1, 0, 0, 0, $this->timezone()));

            return [$start, $end, "en {$name}"];
        }
        if (preg_match('/\b(20\d{2})\b/', $text, $match)) {
            $year = (int) $match[1];

            return [$today->copy()->setDate($year, 1, 1), $today->copy()->setDate($year, 12, 31), "en {$year}"];
        }

        return [];
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Africa/Tunis');
    }
}
