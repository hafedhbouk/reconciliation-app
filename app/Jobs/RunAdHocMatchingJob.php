<?php

namespace App\Jobs;

use App\Enums\MatchingCardinality;
use App\Models\Source;
use App\Notifications\MatchingActionCompletedNotification;
use App\Services\Matching\RuleMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunAdHocMatchingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 0;

    public function __construct(
        public int $sourceAId,
        public int $sourceBId,
        public string $batchReference,
        public ?int $notifyUserId = null
    ) {
    }

    public function handle(RuleMatcher $matcher): void
    {
        $sourceA = Source::query()->findOrFail($this->sourceAId);
        $sourceB = Source::query()->findOrFail($this->sourceBId);

        $rule = new \App\Models\MatchingRule([
            'source_a_id' => $sourceA->id,
            'source_b_id' => $sourceB->id,
            'name' => $sourceA->name . ' ↔ ' . $sourceB->name,
            'cardinality' => MatchingCardinality::ManyToMany,
            'priority' => 0,
            'is_active' => true,
            'criteria' => [
                'tolerance_amount_millimes' => 0,
                'tolerance_days' => 0,
                'excluded_status_raw' => ['a' => [], 'b' => []],
                'primary_key' => $this->detectPrimaryKey($sourceA, $sourceB),
                'verify_fields' => $this->detectVerifyFields($sourceA, $sourceB),
            ],
        ]);

        $summary = $matcher->match($rule, $this->batchReference);

        if ($this->notifyUserId !== null) {
            \App\Models\User::query()->find($this->notifyUserId)?->notify(new MatchingActionCompletedNotification(
                __('Rapprochement « :name » terminé', ['name' => $rule->name]),
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
        Log::error('RunAdHocMatchingJob failed', [
            'source_a_id' => $this->sourceAId,
            'source_b_id' => $this->sourceBId,
            'error' => $e->getMessage(),
        ]);
    }

    private function detectPrimaryKey(Source $sourceA, Source $sourceB): array
    {
        $codes = [$sourceA->code, $sourceB->code];

        if (in_array('SMT', $codes, true)) {
            return ['a' => 'date|amount', 'b' => 'date|amount'];
        }

        if (in_array('WEB', $codes, true) && in_array('BNA', $codes, true)) {
            return [
                'a' => $sourceA->code === 'WEB' ? 'secondary_reference' : 'num_autorisation',
                'b' => $sourceB->code === 'WEB' ? 'secondary_reference' : 'num_autorisation',
            ];
        }

        if (in_array('ALPHA', $codes, true) && in_array('WEB', $codes, true)) {
            return ['a' => 'reference', 'b' => 'reference'];
        }

        return ['a' => 'num_autorisation', 'b' => 'num_autorisation'];
    }

    private function detectVerifyFields(Source $sourceA, Source $sourceB): array
    {
        $codes = [$sourceA->code, $sourceB->code];

        if (in_array('ALPHA', $codes, true) && in_array('WEB', $codes, true)) {
            return [
                ['a' => 'num_autorisation', 'b' => 'secondary_reference'],
                'amount',
                'date',
            ];
        }

        if (in_array('WEB', $codes, true) && in_array('BNA', $codes, true)) {
            return ['amount', 'date'];
        }

        if (in_array('ALPHA', $codes, true) && in_array('BNA', $codes, true)) {
            return ['amount', 'date'];
        }

        return [];
    }
}
