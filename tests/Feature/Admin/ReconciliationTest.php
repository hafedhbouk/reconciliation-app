<?php

use App\Enums\MatchingStatus;
use App\Http\Controllers\Admin\ReconciliationController;
use App\Http\Requests\Admin\StoreManualMatchRequest;
use App\Models\Import;
use App\Models\MatchingDetail;
use App\Models\MatchingResult;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

function makeReconciliationTx(Source $source, MatchingStatus $status = MatchingStatus::Unmatched): NormalizedTransaction
{
    $transaction = Transaction::factory()->create(['source_id' => $source->id]);

    return NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'matching_status' => $status->value,
    ]);
}

test('the index page renders the workbench', function () {
    actingAsAdmin();

    $this->get(route('admin.reconciliation.index'))->assertOk();
});

test('search only returns unmatched normalized transactions', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    $unmatched = makeReconciliationTx($source, MatchingStatus::Unmatched);
    makeReconciliationTx($source, MatchingStatus::Matched);

    $response = $this->getJson(route('admin.reconciliation.search'));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($unmatched->id);
    expect($ids)->toHaveCount(1);
});

test('store creates a manual match spanning both sides', function () {
    $admin = actingAsAdmin();
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    $ntA = makeReconciliationTx($sourceA);
    $ntB = makeReconciliationTx($sourceB);

    $response = $this->post(route('admin.reconciliation.store'), [
        'normalized_transaction_ids_a' => [$ntA->id],
        'normalized_transaction_ids_b' => [$ntB->id],
    ]);

    $result = MatchingResult::query()->sole();
    $response->assertRedirect(route('admin.matching-results.show', $result));

    expect($result->matching_rule_id)->toBeNull();
    expect($result->matched_by)->toBe($admin->id);
    expect(MatchingDetail::query()->where('matching_result_id', $result->id)->count())->toBe(2);
    expect($ntA->fresh()->matching_status->value)->toBe('matched');
    expect($ntB->fresh()->matching_status->value)->toBe('matched');
});

test('store rejects an empty selection on either side', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    $nt = makeReconciliationTx($source);

    $response = $this->post(route('admin.reconciliation.store'), [
        'normalized_transaction_ids_a' => [$nt->id],
        'normalized_transaction_ids_b' => [],
    ]);

    $response->assertSessionHasErrors('normalized_transaction_ids_b');
    expect(MatchingResult::query()->count())->toBe(0);
});

test('store rejects the same transaction id appearing on both sides', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    $nt = makeReconciliationTx($source);

    $response = $this->post(route('admin.reconciliation.store'), [
        'normalized_transaction_ids_a' => [$nt->id],
        'normalized_transaction_ids_b' => [$nt->id],
    ]);

    $response->assertSessionHasErrors('normalized_transaction_ids_b');
    expect(MatchingResult::query()->count())->toBe(0);
});

test('store rejects a transaction that is no longer unmatched', function () {
    actingAsAdmin();
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    $ntA = makeReconciliationTx($sourceA, MatchingStatus::Matched);
    $ntB = makeReconciliationTx($sourceB);

    $response = $this->post(route('admin.reconciliation.store'), [
        'normalized_transaction_ids_a' => [$ntA->id],
        'normalized_transaction_ids_b' => [$ntB->id],
    ]);

    $response->assertSessionHasErrors('normalized_transaction_ids_a');
    expect(MatchingResult::query()->count())->toBe(0);
});

test('manual selection excludes archived imports', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    $a = makeReconciliationTx($source);
    $b = makeReconciliationTx($source);
    $import = Import::factory()->create(['source_id' => $source->id]);
    $a->transaction->update(['import_id' => $import->id]);
    $import->delete();

    $response = $this->getJson(route('admin.reconciliation.search'))->assertOk();
    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$b->id]);
    $this->post(route('admin.reconciliation.store'), [
        'normalized_transaction_ids_a' => [$a->id], 'normalized_transaction_ids_b' => [$b->id],
    ])->assertSessionHasErrors('normalized_transaction_ids_a');
});

test('manual matching rechecks availability after form validation', function () {
    $user = actingAsAdmin();
    $source = Source::factory()->create();
    $a = makeReconciliationTx($source);
    $b = makeReconciliationTx($source);
    $request = StoreManualMatchRequest::create('/', 'POST', [
        'normalized_transaction_ids_a' => [$a->id], 'normalized_transaction_ids_b' => [$b->id],
    ]);
    $request->setUserResolver(fn () => $user);
    // Simulate another worker committing after the form's initial checks.
    $a->update(['matching_status' => 'matched']);
    expect(fn () => app(ReconciliationController::class)->store($request))
        ->toThrow(ValidationException::class);
    expect(MatchingResult::count())->toBe(0);
    expect($b->fresh()->matching_status->value)->toBe('unmatched');
});
