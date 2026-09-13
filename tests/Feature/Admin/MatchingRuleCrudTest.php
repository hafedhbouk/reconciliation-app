<?php

use App\Jobs\RunMatchingRuleJob;
use App\Models\Import;
use App\Models\MatchingRule;
use App\Models\Source;
use Illuminate\Support\Facades\Queue;

test('admin can list matching rules', function () {
    actingAsAdmin();
    MatchingRule::factory()->count(2)->create();

    $this->get(route('admin.matching-rules.index'))->assertOk();
});

test('admin can create a matching rule with criteria built from discrete form fields', function () {
    actingAsAdmin();
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();

    $response = $this->post(route('admin.matching-rules.store'), [
        'name' => 'ALPHA <-> BNA',
        'source_a_id' => $sourceA->id,
        'source_b_id' => $sourceB->id,
        'cardinality' => 'N:M',
        'priority' => 10,
        'is_active' => 1,
        'tolerance_amount_millimes' => 0,
        'tolerance_days' => 0,
        'excluded_status_raw_a' => '',
        'excluded_status_raw_b' => 'Commission, Fee',
    ]);

    $response->assertRedirect(route('admin.matching-rules.index'));
    $this->assertDatabaseHas('matching_rules', ['name' => 'ALPHA <-> BNA', 'priority' => 10]);

    $rule = MatchingRule::query()->where('name', 'ALPHA <-> BNA')->sole();
    expect($rule->criteria['excluded_status_raw']['b'])->toBe(['Commission', 'Fee']);
    expect($rule->criteria['excluded_status_raw']['a'])->toBe([]);
});

test('a rule cannot be created with identical source_a_id and source_b_id', function () {
    actingAsAdmin();
    $source = Source::factory()->create();

    $response = $this->post(route('admin.matching-rules.store'), [
        'name' => 'Invalid rule',
        'source_a_id' => $source->id,
        'source_b_id' => $source->id,
        'cardinality' => 'N:M',
        'priority' => 0,
        'tolerance_amount_millimes' => 0,
        'tolerance_days' => 0,
    ]);

    $response->assertSessionHasErrors('source_b_id');
    $this->assertDatabaseMissing('matching_rules', ['name' => 'Invalid rule']);
});

test('admin can update a matching rule', function () {
    actingAsAdmin();
    $rule = MatchingRule::factory()->create(['priority' => 5]);

    $response = $this->put(route('admin.matching-rules.update', $rule), [
        'name' => $rule->name,
        'source_a_id' => $rule->source_a_id,
        'source_b_id' => $rule->source_b_id,
        'cardinality' => 'N:M',
        'priority' => 99,
        'is_active' => 1,
        'tolerance_amount_millimes' => 0,
        'tolerance_days' => 0,
    ]);

    $response->assertRedirect(route('admin.matching-rules.index'));
    $this->assertDatabaseHas('matching_rules', ['id' => $rule->id, 'priority' => 99]);
});

test('admin can soft delete a matching rule', function () {
    actingAsAdmin();
    $rule = MatchingRule::factory()->create();

    $this->delete(route('admin.matching-rules.destroy', $rule))->assertRedirect(route('admin.matching-rules.index'));

    $this->assertSoftDeleted('matching_rules', ['id' => $rule->id]);
});

test('plain user is forbidden from accessing matching rules', function () {
    actingAsPlainUser();

    $this->get(route('admin.matching-rules.index'))->assertForbidden();
});

test('file matching requires two completed imports from the rule sources', function () {
    actingAsAdmin();
    Queue::fake();
    $rule = MatchingRule::factory()->create();
    $a = Import::factory()->create(['source_id' => $rule->source_a_id, 'status' => 'completed']);
    $b = Import::factory()->create(['source_id' => $rule->source_b_id, 'status' => 'completed']);

    $this->post(route('admin.matching-rules.run', $rule), ['import_a_id' => $a->id])
        ->assertSessionHasErrors('import_b_id');
    $this->post(route('admin.matching-rules.run', $rule), ['import_a_id' => $b->id, 'import_b_id' => $a->id])
        ->assertSessionHasErrors(['import_a_id', 'import_b_id']);
    Queue::assertNothingPushed();

    $this->post(route('admin.matching-rules.run', $rule), ['import_a_id' => $a->id, 'import_b_id' => $b->id])
        ->assertRedirect(route('admin.matching-rules.index'));
    Queue::assertPushed(RunMatchingRuleJob::class,
        fn ($job) => $job->importIdA === $a->id && $job->importIdB === $b->id);
});

test('ad hoc matching rejects imports that have not completed', function () {
    actingAsAdmin();
    Queue::fake();
    $a = Import::factory()->create(['status' => 'pending']);
    $b = Import::factory()->create(['status' => 'completed']);

    $this->post(route('admin.matching-rules.run-ad-hoc'), ['import_a_id' => $a->id, 'import_b_id' => $b->id])
        ->assertSessionHasErrors('import_a_id');
    Queue::assertNothingPushed();
});
