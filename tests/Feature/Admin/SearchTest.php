<?php

use App\Enums\MatchingStatus;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;

function makeSearchTx(Source $source, string $reference, int $amount, string $date, MatchingStatus $status = MatchingStatus::Unmatched, ?string $canal = null): NormalizedTransaction
{
    $transaction = Transaction::factory()->create([
        'source_id' => $source->id,
        'external_reference' => $reference,
        'amount_millimes' => $amount,
        'transaction_date' => $date,
        'canal' => $canal,
    ]);

    return NormalizedTransaction::factory()->create([
        'transaction_id' => $transaction->id,
        'normalized_reference' => $reference,
        'normalized_amount_millimes' => $amount,
        'normalized_date' => $date,
        'matching_status' => $status->value,
    ]);
}

test('the search index page renders for a user with search.viewAny', function () {
    actingAsAdmin();

    $this->get(route('admin.search.index'))->assertOk();
});

test('plain user without search.viewAny is forbidden', function () {
    actingAsPlainUser();

    $this->get(route('admin.search.index'))->assertForbidden();
});

test('search returns rows of any matching status, unlike reconciliation search', function () {
    actingAsAdmin();
    $source = Source::factory()->create();
    $matched = makeSearchTx($source, '111111', 1000, '2026-01-01', MatchingStatus::Matched);

    $response = $this->getJson(route('admin.search.data'));

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($matched->id);
});

test('search filters by source, reference, amount range, date range, status and canal', function () {
    actingAsAdmin();
    $sourceA = Source::factory()->create();
    $sourceB = Source::factory()->create();
    $target = makeSearchTx($sourceA, '843500', 50000, '2026-01-15', MatchingStatus::Matched, 'WEB');
    makeSearchTx($sourceB, '843500', 50000, '2026-01-15', MatchingStatus::Matched, 'WEB'); // different source
    makeSearchTx($sourceA, '999999', 50000, '2026-01-15', MatchingStatus::Matched, 'WEB'); // different reference
    makeSearchTx($sourceA, '843500', 999, '2026-01-15', MatchingStatus::Matched, 'WEB'); // outside amount range
    makeSearchTx($sourceA, '843500', 50000, '2025-06-01', MatchingStatus::Matched, 'WEB'); // outside date range
    makeSearchTx($sourceA, '843500', 50000, '2026-01-15', MatchingStatus::Conflict, 'WEB'); // wrong status
    makeSearchTx($sourceA, '843500', 50000, '2026-01-15', MatchingStatus::Matched, 'USSD'); // wrong canal

    $response = $this->getJson(route('admin.search.data', [
        'source_id' => $sourceA->id,
        'reference' => '843500',
        'amount_min' => 40000,
        'amount_max' => 60000,
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-31',
        'matching_status' => MatchingStatus::Matched->value,
        'canal' => 'WEB',
    ]));

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toHaveCount(1);
    expect($ids)->toContain($target->id);
});
