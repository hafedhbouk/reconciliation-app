<?php

namespace App\Jobs;

use App\Enums\MatchingCardinality;
use App\Models\Import;
use App\Models\MatchingRule;
use App\Models\User;
use App\Notifications\MatchingActionCompletedNotification;
use App\Services\Matching\FileComparisonRules;
use App\Services\Matching\RuleMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAdHocMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(
        public int $importAId,
        public int $importBId,
        public string $batchReference,
        public ?int $notifyUserId = null
    ) {}

    public function handle(RuleMatcher $matcher): void
    {
        [$rule, $summary] = DB::transaction(function () use ($matcher) {
            $imports = Import::query()->whereIn('id', [$this->importAId, $this->importBId])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($imports->count() !== 2) {
                throw new \InvalidArgumentException('Deux fichiers distincts et actifs sont nécessaires.');
            }
            $importA = $imports->get($this->importAId);
            $importB = $imports->get($this->importBId);

            $sourceA = $importA->source;
            $sourceB = $importB->source;

            // Keep file comparisons separate from configurable global rules.
            // Always use the requested fields, including when an older rule exists.
            $rule = MatchingRule::query()->updateOrCreate(
                ['name' => 'Fichiers : '.$sourceA->code.' ↔ '.$sourceB->code],
                [
                    'source_a_id' => $sourceA->id,
                    'source_b_id' => $sourceB->id,
                    'cardinality' => MatchingCardinality::ManyToMany,
                    'priority' => 0,
                    'is_active' => false,
                    'criteria' => app(FileComparisonRules::class)->criteria($sourceA, $sourceB),
                ],
            );

            $summary = $matcher->match($rule, $this->batchReference, $this->importAId, $this->importBId);

            return [$rule, $summary];
        });

        if ($this->notifyUserId !== null) {
            User::query()->find($this->notifyUserId)?->notify(new MatchingActionCompletedNotification(
                __('Rapprochement « :name » terminé', ['name' => $rule->name]),
                [
                    __(':count références traitées', ['count' => $summary->referencesConsidered]),
                    __(':count rapprochées', ['count' => $summary->matched]),
                    __(':count conflits', ['count' => $summary->conflicts]),
                    __(':count lignes uniquement dans le fichier A', ['count' => $summary->unmatchedA]),
                    __(':count lignes uniquement dans le fichier B', ['count' => $summary->unmatchedB]),
                ],
            ));
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('RunAdHocMatchingJob failed', [
            'import_a_id' => $this->importAId,
            'import_b_id' => $this->importBId,
            'error' => $e->getMessage(),
        ]);
    }
}
