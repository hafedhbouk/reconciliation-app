<?php

use App\Models\MatchingResult;

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
