<?php

namespace App\Jobs;

use App\DataTransferObjects\MatchingRunSummary;
use App\Enums\MatchingCardinality;
use App\Models\Import;
use App\Models\MatchingRule;
use App\Models\User;
use App\Notifications\MatchingActionCompletedNotification;
use App\Services\Matching\FileComparisonRules;
use App\Services\Matching\RuleMatcher;
use App\Services\OperationTracker;
use App\Services\ResultEmails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rapproche deux FICHIERS précis choisis par l'utilisateur (« comparaison de fichiers »).
 *
 * Étapes :
 *   1. verrouiller les deux imports (deux comparaisons simultanées des mêmes fichiers attendent leur tour) ;
 *   2. créer ou mettre à jour une règle technique « Fichiers : ALPHA ↔ BNA », INACTIVE
 *      (elle ne sera donc jamais lancée par « Lancer tout »), avec les critères de FileComparisonRules ;
 *   3. lancer RuleMatcher::match() sur ces deux fichiers ;
 *   4. prévenir l'utilisateur avec le résumé chiffré.
 * (Le fonctionnement général des jobs est expliqué dans ProcessImportJob.php.)
 */
class RunAdHocMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(
        public int $importAId,
        public int $importBId,
        public string $batchReference,
        public ?int $notifyUserId = null,
        public ?int $operationId = null, // suivi « Mes opérations » créé au clic (voir OperationTracker)
    ) {}

    public function handle(RuleMatcher $matcher): void
    {
        // Suivi de l'avancement (« Mes opérations ») : le moteur y écrit le total puis les lignes traitées.
        $tracker = app(OperationTracker::class);
        $names = Import::query()->whereIn('id', [$this->importAId, $this->importBId])->pluck('original_filename', 'id');
        $operation = $tracker->resolve($this->operationId, 'matching',
            'Rapprochement '.($names[$this->importAId] ?? '?').' ↔ '.($names[$this->importBId] ?? '?'), $this->notifyUserId);
        $tracker->start($operation, null, 'Préparation des deux fichiers');

        try {
            [$rule, $summary] = $this->matchFiles($matcher->trackWith($operation));
        } catch (Throwable $e) {
            $tracker->fail($operation, 'Rapprochement interrompu : '.$e->getMessage());
            app(ResultEmails::class)->operationFailed($operation->id);

            throw $e;
        }

        $tracker->finish($operation, [
            'rapprochements' => $summary->matched,
            'conflits' => $summary->conflicts,
            'exclusives_a' => $summary->unmatchedA,
            'exclusives_b' => $summary->unmatchedB,
        ], route('admin.reconciliation.unmatched', ['import_a_id' => $this->importAId, 'import_b_id' => $this->importBId], false), [
            'kind' => 'comparison', 'import_a_id' => $this->importAId, 'import_b_id' => $this->importBId, 'batch_reference' => $this->batchReference,
        ]);

        $this->notify($rule, $summary);
        // Envoi des résultats par e-mail s'il a été demandé (job séparé : un échec d'envoi ne touche pas le rapprochement).
        app(ResultEmails::class)->operationCompleted($operation->id);
    }

    /** Comparaison des deux fichiers, dans une transaction : renvoie [règle, résumé]. */
    private function matchFiles(RuleMatcher $matcher): array
    {
        // La transaction renvoie un tableau [règle, résumé], qu'on « déstructure » en deux variables.
        return DB::transaction(function () use ($matcher) {
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
            // updateOrCreate(critères de recherche, valeurs) : met à jour la règle portant ce nom, ou la crée.
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
    }

    /** Notification de fin dans la cloche, avec le résumé chiffré. */
    private function notify(MatchingRule $rule, MatchingRunSummary $summary): void
    {
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
        app(OperationTracker::class)->failById($this->operationId, 'Rapprochement interrompu : '.$e->getMessage());
        app(ResultEmails::class)->operationFailed($this->operationId);
    }
}
