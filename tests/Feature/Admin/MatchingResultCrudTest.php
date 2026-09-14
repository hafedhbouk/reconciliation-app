<?php

use App\Models\MatchingResult;
use App\Models\NormalizedTransaction;

test('admin can list matching results', function () {
    actingAsAdmin();
    MatchingResult::factory()->count(2)->create();

    $this->get(route('admin.matching-results.index'))->assertOk();
});

test('admin can view a matching result detail page', function () {
    actingAsAdmin();
    $result = MatchingResult::factory()->create();

    $this->get(route('admin.matching-results.show', $result))->assertOk();
});

test('the datatables endpoint returns matching results as json', function () {
    actingAsAdmin();
    MatchingResult::factory()->count(3)->create();

    $response = $this->getJson(route('admin.matching-results.data'));

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
});

test('plain user is forbidden from viewing matching results', function () {
    actingAsPlainUser();
    $result = MatchingResult::factory()->create();

    $this->get(route('admin.matching-results.index'))->assertForbidden();
    $this->get(route('admin.matching-results.show', $result))->assertForbidden();
});

test('cancelling a result releases rows and preserves the audit details', function () {
    actingAsAdmin();
    $result = MatchingResult::factory()->create(['status' => 'matched']);
    $row = NormalizedTransaction::factory()->create(['matching_status' => 'matched']);
    $result->matchingDetails()->create(['normalized_transaction_id' => $row->id, 'side' => 'a']);

    $this->delete(route('admin.matching-results.destroy', $result))->assertRedirect();

    expect($row->fresh()->matching_status->value)->toBe('unmatched');
    expect($result->fresh()->trashed())->toBeTrue();
    expect($result->matchingDetails()->count())->toBe(1);
});

test('cancelling a conflict retains the status from another active result', function () {
    actingAsAdmin();
    $row = NormalizedTransaction::factory()->create(['matching_status' => 'conflict']);
    $matched = MatchingResult::factory()->create(['status' => 'matched']);
    $conflict = MatchingResult::factory()->create(['status' => 'conflict']);
    foreach ([$matched, $conflict] as $result) {
        $result->matchingDetails()->create(['normalized_transaction_id' => $row->id, 'side' => 'a']);
    }
    $this->delete(route('admin.matching-results.destroy', $conflict))->assertRedirect();
    expect($row->fresh()->matching_status->value)->toBe('matched');
    $this->delete(route('admin.matching-results.destroy', $matched))->assertRedirect();
    expect($row->fresh()->matching_status->value)->toBe('unmatched');
});

test('manual result detail renders without a matching rule', function () {
    actingAsAdmin();
    $result = MatchingResult::factory()->create(['matching_rule_id' => null]);
    $this->get(route('admin.matching-results.show', $result))->assertOk();
});

test('a failed cancellation rolls back exceptions and row statuses', function () {
    actingAsAdmin();
    $result = MatchingResult::factory()->create(['status' => 'conflict']);
    $row = NormalizedTransaction::factory()->create(['matching_status' => 'conflict']);
    $result->matchingDetails()->create(['normalized_transaction_id' => $row->id, 'side' => 'a']);
    $exception = $result->exceptions()->create(['type' => 'conflict', 'status' => 'open']);
    MatchingResult::deleting(fn () => throw new RuntimeException('cancel failure'));
    $this->withoutExceptionHandling();
    expect(fn () => $this->delete(route('admin.matching-results.destroy', $result)))
        ->toThrow(RuntimeException::class, 'cancel failure');
    expect($result->fresh()->trashed())->toBeFalse();
    expect($exception->fresh()->trashed())->toBeFalse();
    expect($row->fresh()->matching_status->value)->toBe('conflict');
});
