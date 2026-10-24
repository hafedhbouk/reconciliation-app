<?php

namespace App\Services\Chatbot;

use App\Models\Source;
use App\Models\User;
use App\Services\Chatbot\Intents\ConflictsIntent;
use App\Services\Chatbot\Intents\ExceptionsIntent;
use App\Services\Chatbot\Intents\FailedOperationsIntent;
use App\Services\Chatbot\Intents\ImportsIntent;
use App\Services\Chatbot\Intents\Intent;
use App\Services\Chatbot\Intents\MatchingCountIntent;
use App\Services\Chatbot\Intents\MatchingRateIntent;
use App\Services\Chatbot\Intents\MonthlyTrendIntent;
use App\Services\Chatbot\Intents\UnmatchedLinesIntent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chatbot métier (Phase 6) : répond en français aux questions sur les données
 * de l'application, dans la limite des droits de l'utilisateur connecté.
 *
 * Fonctionnement, entièrement sur le serveur (aucun service externe, aucune
 * donnée transmise hors de l'application) :
 *   1. QuestionParser lit la période et les sources citées ;
 *   2. chaque intention métier donne un score ; la meilleure est retenue ;
 *   3. la permission de cette intention est vérifiée ;
 *   4. l'intention calcule la réponse avec un service métier existant
 *      (tableau de bord analytique, suivi des opérations…).
 * Aucun SQL n'est construit à partir du texte de la question. Une question non
 * reconnue reçoit la liste des questions prises en charge, jamais une réponse inventée.
 */
class Chatbot
{
    /** Ordre = priorité en cas d'égalité de score. */
    private const INTENTS = [
        FailedOperationsIntent::class,
        UnmatchedLinesIntent::class,
        MonthlyTrendIntent::class,
        ExceptionsIntent::class,
        MatchingRateIntent::class,
        ConflictsIntent::class,
        ImportsIntent::class,
        MatchingCountIntent::class,
    ];

    public function __construct(private QuestionParser $parser) {}

    public function ask(User $user, string $text): Answer
    {
        $question = $this->parser->parse($text, Source::query()->pluck('code')->all());

        if ($question->mentions('^(bonjour|bonsoir|salut|aide|help)\b', '\baide\b', 'que (peux|sais) tu', 'quelles? questions', 'comment (ca marche|t utiliser)')) {
            return new Answer('ok', 'Bonjour ! Je réponds aux questions sur les données de l’application que vous avez le droit de consulter. Par exemple :',
                suggestions: $this->examples($user), intent: 'aide');
        }

        $intent = $this->match($question);
        if ($intent === null) {
            return new Answer('non_pris_en_charge',
                'Je ne sais pas répondre à cette question : elle ne correspond à aucune des questions prises en charge. Je ne donne que des chiffres calculés sur les données de l’application. Essayez par exemple :',
                suggestions: $this->examples($user));
        }
        if ($intent->permission() !== null && ! $user->can($intent->permission())) {
            return new Answer('interdit', 'Vous n’avez pas le droit de consulter '.$intent->forbiddenSubject().' : je ne peux pas répondre à cette question.',
                suggestions: $this->examples($user), intent: $intent->name());
        }

        try {
            return $intent->answer($question, $user);
        } catch (Throwable $e) {
            Log::error('Chatbot : calcul impossible', ['intent' => $intent->name(), 'user_id' => $user->id, 'error' => $e->getMessage()]);

            return new Answer('erreur', 'Un incident technique empêche de calculer cette réponse. Réessayez plus tard.', intent: $intent->name());
        }
    }

    /** Questions d'exemple que cet utilisateur a le droit de poser. @return list<string> */
    public function examples(User $user): array
    {
        return collect($this->intents())
            ->filter(fn (Intent $intent) => $intent->permission() === null || $user->can($intent->permission()))
            ->map(fn (Intent $intent) => $intent->example())->values()->all();
    }

    private function match(Question $question): ?Intent
    {
        $best = null;
        $bestScore = 0;
        foreach ($this->intents() as $intent) {
            $score = $intent->score($question);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$intent, $score];
            }
        }

        return $best;
    }

    /** @return list<Intent> */
    private function intents(): array
    {
        return array_map(fn (string $class) => app($class), self::INTENTS);
    }
}
