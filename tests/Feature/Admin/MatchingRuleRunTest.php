<?php

use App\Jobs\DetectDuplicatesJob;
use App\Jobs\RunMatchingRuleJob;
use App\Jobs\SweepUnmatchedJob;
use App\Models\MatchingRule;
use Illuminate\Support\Facades\Bus;

test('run dispatches RunMatchingRuleJob for the given rule', function () {
    actingAsAdmin();
    Bus::fake();
    $rule = MatchingRule::factory()->create();

    $this->post(route('admin.matching-rules.run', $rule))->assertRedirect(route('admin.matching-rules.index'));

    Bus::assertDispatched(RunMatchingRuleJob::class, fn ($job) => $job->matchingRuleId === $rule->id);
});

test('run-all dispatches a chain of active rules ordered by priority plus trailing sweep jobs', function () {
    actingAsAdmin();
    Bus::fake();

    $firstRule = MatchingRule::factory()->create(['priority' => 5, 'is_active' => true]);
    $secondRule = MatchingRule::factory()->create(['priority' => 50, 'is_active' => true]);
    MatchingRule::factory()->create(['priority' => 1, 'is_active' => false]);

    $this->post(route('admin.matching-rules.run-all'))->assertRedirect(route('admin.matching-rules.index'));

    Bus::assertChained([
        RunMatchingRuleJob::class,
        RunMatchingRuleJob::class,
        DetectDuplicatesJob::class,
        SweepUnmatchedJob::class,
    ]);
});

test('a user without matching-rules.update cannot run a rule', function () {
    actingAsPlainUser();
    Bus::fake();
    $rule = MatchingRule::factory()->create();

    $this->post(route('admin.matching-rules.run', $rule))->assertForbidden();

    Bus::assertNotDispatched(RunMatchingRuleJob::class);
});

test('a user without matching-rules.update cannot trigger duplicate detection or the sweep', function () {
    actingAsPlainUser();
    Bus::fake();

    $this->post(route('admin.matching-rules.detect-duplicates'))->assertForbidden();
    $this->post(route('admin.matching-rules.sweep-unmatched'))->assertForbidden();

    Bus::assertNotDispatched(DetectDuplicatesJob::class);
    Bus::assertNotDispatched(SweepUnmatchedJob::class);
});
