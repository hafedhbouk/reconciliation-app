<?php

use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;

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

test('Alpha BNA result displays authorization date and amount fields', function () {
    actingAsAdmin();
    $authorization = '012345';
    $alphaReference = '999999999';
    $alpha = Source::factory()->create(['code' => 'ALPHA', 'name' => 'Alpha']);
    $bna = Source::factory()->create(['code' => 'BNA', 'name' => 'BNA']);
    $rule = MatchingRule::factory()->create([
        'source_a_id' => $alpha->id,
        'source_b_id' => $bna->id,
    ]);
    $result = MatchingResult::factory()->create(['matching_rule_id' => $rule->id]);

    $alphaTransaction = Transaction::factory()->create([
        'source_id' => $alpha->id,
        'external_reference' => $alphaReference,
        'raw_payload' => ['num_autorisation' => $authorization],
    ]);
    NormalizedTransaction::factory()->create([
        'transaction_id' => $alphaTransaction->id,
        'normalized_reference' => $alphaReference,
        'normalized_amount_millimes' => 178000,
        'normalized_date' => '2026-05-01',
    ]);

    $bnaTransaction = Transaction::factory()->create([
        'source_id' => $bna->id,
        'external_reference' => null,
        'raw_payload' => ['num_autorisation' => $authorization],
    ]);
    NormalizedTransaction::factory()->create([
        'transaction_id' => $bnaTransaction->id,
        'normalized_amount_millimes' => 178000,
        'normalized_date' => '2026-05-01',
    ]);

    $this->get(route('admin.matching-results.show', $result))
        ->assertOk()
        ->assertSee('NUM_AUTO')
        ->assertSee('DAT_ENC')
        ->assertSee('MONTANT_ENCAISS')
        ->assertSee('N° autorisation')
        ->assertSee('Date')
        ->assertSee('Montant')
        ->assertSee($authorization)
        ->assertDontSee($alphaReference);
});

test('Alpha WEB result displays both reference and receipt fields', function () {
    actingAsAdmin();
    $alphaReference = '123456789';
    $webReference = '987654321';
    $authorization = '004321';
    $alpha = Source::factory()->create(['code' => 'ALPHA', 'name' => 'Alpha']);
    $web = Source::factory()->create(['code' => 'WEB', 'name' => 'WEB / STEG']);
    $rule = MatchingRule::factory()->create([
        'source_a_id' => $alpha->id,
        'source_b_id' => $web->id,
    ]);
    $result = MatchingResult::factory()->create(['matching_rule_id' => $rule->id]);

    $alphaTransaction = Transaction::factory()->create([
        'source_id' => $alpha->id,
        'external_reference' => $alphaReference,
        'raw_payload' => ['reference' => $alphaReference, 'num_autorisation' => $authorization],
    ]);
    NormalizedTransaction::factory()->create([
        'transaction_id' => $alphaTransaction->id,
        'normalized_reference' => $alphaReference,
        'normalized_amount_millimes' => 75000,
        'normalized_date' => '2026-05-01',
    ]);

    $webTransaction = Transaction::factory()->create([
        'source_id' => $web->id,
        'external_reference' => $webReference,
        'raw_payload' => ['reference' => $webReference, 'secondary_reference' => $authorization],
    ]);
    NormalizedTransaction::factory()->create([
        'transaction_id' => $webTransaction->id,
        'normalized_reference' => $webReference,
        'normalized_amount_millimes' => 75000,
        'normalized_date' => '2026-05-01',
    ]);

    $this->get(route('admin.matching-results.show', $result))
        ->assertOk()
        ->assertSee('REFERENCE')
        ->assertSee('NUM_AUTO')
        ->assertSee('DAT_ENC')
        ->assertSee('MONTANT_ENCAISS')
        ->assertSee('reference')
        ->assertSee('recu_paie')
        ->assertSee('date_paiement')
        ->assertSee('montant')
        ->assertSee($alphaReference)
        ->assertSee($webReference)
        ->assertSee($authorization);
});

test('BNA WEB result displays authorization and receipt comparison fields', function () {
    actingAsAdmin();
    $authorization = '006321';
    $webReference = '888777666';
    $bna = Source::factory()->create(['code' => 'BNA', 'name' => 'BNA']);
    $web = Source::factory()->create(['code' => 'WEB', 'name' => 'WEB / STEG']);
    $rule = MatchingRule::factory()->create([
        'source_a_id' => $bna->id,
        'source_b_id' => $web->id,
    ]);
    $result = MatchingResult::factory()->create(['matching_rule_id' => $rule->id]);

    $bnaTransaction = Transaction::factory()->create([
        'source_id' => $bna->id,
        'external_reference' => null,
        'raw_payload' => ['num_autorisation' => $authorization],
    ]);
    NormalizedTransaction::factory()->create([
        'transaction_id' => $bnaTransaction->id,
        'normalized_amount_millimes' => 13000,
        'normalized_date' => '2026-05-15',
    ]);

    $webTransaction = Transaction::factory()->create([
        'source_id' => $web->id,
        'external_reference' => $webReference,
        'raw_payload' => ['reference' => $webReference, 'secondary_reference' => $authorization],
    ]);
    NormalizedTransaction::factory()->create([
        'transaction_id' => $webTransaction->id,
        'normalized_reference' => $webReference,
        'normalized_amount_millimes' => 13000,
        'normalized_date' => '2026-05-15',
    ]);

    $this->get(route('admin.matching-results.show', $result))
        ->assertOk()
        ->assertSee('N° autorisation')
        ->assertSee('Date')
        ->assertSee('Montant')
        ->assertSee('recu_paie')
        ->assertSee('date_paiement')
        ->assertSee('montant')
        ->assertSee($authorization)
        ->assertDontSee($webReference);
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
