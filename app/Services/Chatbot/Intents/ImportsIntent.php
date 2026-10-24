<?php

namespace App\Services\Chatbot\Intents;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\User;
use App\Services\Chatbot\Answer;
use App\Services\Chatbot\Question;
use App\Support\DateDisplay;
use Illuminate\Database\Eloquent\Builder;

/** « Combien de fichiers ont été importés ce mois-ci ? » */
class ImportsIntent extends Intent
{
    public function name(): string
    {
        return 'imports';
    }

    public function example(): string
    {
        return 'Combien de fichiers ont été importés ce mois-ci ?';
    }

    public function permission(): ?string
    {
        return 'imports.viewAny';
    }

    public function forbiddenSubject(): string
    {
        return 'les imports';
    }

    public function score(Question $question): int
    {
        return $question->mentions('\bimport', '\bfichiers?\b') && $question->mentions('combien', '\bnombre\b', '\bdernier', '\bquels?\b', '\bliste')
            ? 7 : 0;
    }

    public function answer(Question $question, User $user): Answer
    {
        [$from, $to] = self::utcBounds($question);
        $base = fn () => Import::query() // imports supprimés exclus (SoftDeletes)
            ->when($from, fn (Builder $q) => $q->whereBetween('created_at', [$from, $to]))
            ->when($question->sources !== [], fn (Builder $q) => $q->whereHas('source', fn (Builder $s) => $s->whereIn('code', $question->sources)));
        $basis = 'Fichiers importés et non supprimés, selon leur date d’import.';
        $total = $base()->count();
        if ($total === 0) {
            return new Answer('vide', 'Aucun fichier n’a été importé '.$question->periodText().$question->sourcesText().'.', basis: $basis, intent: $this->name());
        }
        $byStatus = $base()->selectRaw('status, COUNT(*) AS n')->groupBy('status')->pluck('n', 'status');
        $rows = collect(ImportStatus::cases())->filter(fn ($status) => ($byStatus[$status->value] ?? 0) > 0)
            ->map(fn ($status) => ['Statut : '.$status->label(), self::number((int) $byStatus[$status->value])])->values()->all();
        foreach ($base()->with('source')->latest('id')->limit(5)->get() as $import) {
            $rows[] = [$import->original_filename.' ('.$import->source?->code.')', DateDisplay::dateTime($import->created_at) ?? '—'];
        }

        return new Answer('ok',
            self::sentence($question->periodText()).$question->sourcesText().', '.self::number($total).($total > 1 ? ' fichiers ont été importés.' : ' fichier a été importé.')
                .' Les plus récents sont listés ci-dessous.',
            $rows, $basis, intent: $this->name());
    }
}
