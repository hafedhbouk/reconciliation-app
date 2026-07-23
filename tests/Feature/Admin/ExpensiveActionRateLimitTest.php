<?php

use App\Models\MatchingRule;
use Illuminate\Support\Facades\Bus;

test('running a matching rule is throttled after 10 requests per minute', function () {
    actingAsAdmin();
    Bus::fake();
    $rule = MatchingRule::factory()->create();

    for ($i = 0; $i < 10; $i++) {
        $this->post(route('admin.matching-rules.run', $rule))->assertRedirect();
    }

    $this->post(route('admin.matching-rules.run', $rule))->assertStatus(429);
});

test('exporting search results is throttled after 10 requests per minute', function () {
    actingAsAdmin();

    for ($i = 0; $i < 10; $i++) {
        $this->get(route('admin.search.export', 'csv'))->assertOk();
    }

    $this->get(route('admin.search.export', 'csv'))->assertStatus(429);
});
