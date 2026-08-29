<?php

namespace App\Jobs;

/**
 * Exécute une règle de rapprochement unique en arrière-plan.
 *
 * Déclenché soit individuellement (bouton "Lancer" sur une règle), soit
 * dans le cadre d'un "Lancer tout". Le paramètre notifyUserId permet
 * d'envoyer une notification à l'utilisateur déclencheur à la fin du
 * traitement ; MatchingResult.matched_by reste null car le matching
 * automatique n'a pas d'opérateur humain.
 */
use App\Models\MatchingRule;
use App\Models\User;
use App\Notifications\MatchingActionCompletedNotification;
use App\Services\Matching\RuleMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunMatchingRuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    /**
     * $notifyUserId is delivery-only (who should be told this finished), not
     * provenance -- MatchingResult.matched_by stays null for every rule-driven
     * match regardless. Real need: this session's own Phase 3 manual
     * verification measured some rules taking 15-20 minutes, long enough that
     * the flash message on dispatch ("launched") is stale by completion time.
     */
    public function __construct(public int $matchingRuleId, public string $batchReference, public ?int $notifyUserId = null)
    {
    }

    public function handle(RuleMatcher $matcher): void
    {
        $rule = MatchingRule::query()->findOrFail($this->matchingRuleId);

        $summary = $matcher->match($rule, $this->batchReference);

        if ($this->notifyUserId !== null) {
            User::query()->find($this->notifyUserId)?->notify(new MatchingActionCompletedNotification(
                __('Règle « :name » terminée', ['name' => $rule->name]),
                [
                    __(':count références traitées', ['count' => $summary->referencesConsidered]),
                    __(':count rapprochées', ['count' => $summary->matched]),
                    __(':count conflits', ['count' => $summary->conflicts]),
                ],
            ));
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('RunMatchingRuleJob failed', [
            'matching_rule_id' => $this->matchingRuleId,
            'batch_reference' => $this->batchReference,
            'error' => $e->getMessage(),
        ]);
    }
}
