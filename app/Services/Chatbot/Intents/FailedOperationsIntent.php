<?php

namespace App\Services\Chatbot\Intents;

use App\Models\Operation;
use App\Models\User;
use App\Services\Analytics\OperationAnalytics;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;

/**
 * « Quels sont les derniers traitements échoués ? »
 * Les opérations de l'utilisateur seulement, sauf pour un administrateur (OperationPolicy::viewAll).
 */
class FailedOperationsIntent extends Intent
{
    public function __construct(private OperationAnalytics $operations) {}

    public function name(): string
    {
        return 'traitements_echoues';
    }

    public function example(): string
    {
        return 'Quels sont les derniers traitements échoués ?';
    }

    public function score(Question $question): int
    {
        return $question->mentions('\bechou', '\bechec', 'en erreur', '\bplante', 'n a pas abouti', 'interrompu') ? 9 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        $all = $user->can('viewAll', Operation::class);
        $summary = $this->operations->summary(array_filter([
            'date_debut' => $question->from?->format('Y-m-d'),
            'date_fin' => $question->to?->format('Y-m-d'),
        ]), $all ? null : $user->id);
        $scope = $all ? 'toutes opérations confondues' : 'parmi vos opérations';
        $basis = 'Suivi des opérations (imports, rapprochements, comparaisons, exports), selon la date de lancement'
            .($all ? ', pour tous les utilisateurs (droit administrateur).' : ', limité à vos propres opérations.');

        if ($summary['echecs'] === 0) {
            return new Answer('vide', 'Aucun traitement n’a échoué '.$question->periodText().' ('.$scope.', '.self::count($summary['total'], 'opération', 'opérations').' au total).',
                basis: $basis, intent: $this->name());
        }
        $rows = array_map(fn (array $failure) => [
            $failure['libelle'].' — '.$failure['date'].($all && $failure['utilisateur'] ? ' — '.$failure['utilisateur'] : ''),
            (string) ($failure['erreur'] ?? 'Erreur non précisée'),
        ], $summary['derniers_echecs']);

        return new Answer('ok',
            self::sentence($question->periodText()).', '.self::number($summary['echecs']).($summary['echecs'] > 1 ? ' traitements ont échoué' : ' traitement a échoué')
                .' ('.$scope.', sur '.self::count($summary['total'], 'opération', 'opérations').'). '
                .(count($rows) < $summary['echecs'] ? 'Voici les '.count($rows).' plus récents :' : 'Détail :'),
            $rows, $basis, intent: $this->name());
    }
}
