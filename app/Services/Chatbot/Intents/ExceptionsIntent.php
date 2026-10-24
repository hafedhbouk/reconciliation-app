<?php

namespace App\Services\Chatbot\Intents;

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Models\ExceptionRecord;
use App\Models\User;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;
use Illuminate\Database\Eloquent\Builder;

/** « Combien d'exceptions sont ouvertes ? » */
class ExceptionsIntent extends Intent
{
    public function name(): string
    {
        return 'exceptions';
    }

    public function example(): string
    {
        return 'Combien d’exceptions sont encore ouvertes ?';
    }

    public function permission(): ?string
    {
        return 'exceptions.viewAny';
    }

    public function forbiddenSubject(): string
    {
        return 'les exceptions';
    }

    public function score(Question $question): int
    {
        return $question->mentions('\bexception') ? 9 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        [$from, $to] = self::utcBounds($question);
        $base = fn () => ExceptionRecord::query()->when($from, fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]));
        $byStatus = $base()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $basis = 'Exceptions enregistrées (anomalies à traiter), selon leur date de création ; statut actuel.';
        if ($byStatus->sum() === 0) {
            return new Answer('vide', 'Aucune exception n’a été créée '.$question->periodText().'.', basis: $basis, intent: $this->name());
        }
        $open = ($byStatus[ExceptionStatus::Open->value] ?? 0) + ($byStatus[ExceptionStatus::InReview->value] ?? 0);
        $rows = collect(ExceptionStatus::cases())->filter(fn ($status) => ($byStatus[$status->value] ?? 0) > 0)
            ->map(fn ($status) => ['Statut : '.$status->label(), self::number($byStatus[$status->value])])->values()->all();
        $byType = $base()->whereIn('status', [ExceptionStatus::Open->value, ExceptionStatus::InReview->value])
            ->selectRaw('type, COUNT(*) AS n')->groupBy('type')->pluck('n', 'type');
        foreach (ExceptionType::cases() as $type) {
            if (($byType[$type->value] ?? 0) > 0) {
                $rows[] = ['À traiter, type : '.$type->label(), self::number((int) $byType[$type->value])];
            }
        }

        return new Answer('ok',
            self::sentence($question->periodText()).', '.self::count($byStatus->sum(), 'exception', 'exceptions').' : '.self::number($open)
                .' encore à traiter (ouvertes ou en cours d’examen).',
            $rows, $basis, intent: $this->name());
    }
}
