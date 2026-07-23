<?php

namespace App\Jobs;

use App\Models\MatchingRule;
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

    public function __construct(public int $matchingRuleId, public string $batchReference)
    {
    }

    public function handle(RuleMatcher $matcher): void
    {
        $rule = MatchingRule::query()->findOrFail($this->matchingRuleId);

        $matcher->match($rule, $this->batchReference);
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
