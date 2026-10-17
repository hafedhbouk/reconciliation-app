<?php

use App\Models\AuditLog;

test('audit listing sorts and paginates without returning large payloads', function () {
    $user = actingAsAdmin();
    $logs = collect(range(1, 12))->map(function ($day) use ($user) {
        $log = new AuditLog([
            'user_id' => $user->id, 'event' => 'listing-test',
            'old_values' => ['text' => str_repeat('x', 100000)],
            'new_values' => ['text' => str_repeat('y', 100000)],
        ]);
        $log->created_at = sprintf('2026-09-%02d 12:00:00', $day);
        $log->save();

        return $log;
    });
    $params = [
        'draw' => 1, 'start' => 0, 'length' => 10,
        'columns' => [['data' => 'date', 'name' => 'created_at', 'orderable' => 'true', 'searchable' => 'false'],
            ['data' => 'event', 'name' => 'event', 'orderable' => 'true', 'searchable' => 'true', 'search' => ['value' => 'listing-test']]],
        'order' => [['column' => 0, 'dir' => 'desc']],
    ];
    $response = $this->getJson(route('admin.audit-logs.data', $params));
    $response->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('recordsFiltered', 12)
        ->assertJsonPath('data.0.id', $logs->last()->id)->assertJsonPath('data.0.user', $user->name);
    expect($response->json('data.0'))->not->toHaveKeys(['old_values', 'new_values', 'user_agent']);
    $params['start'] = 10;
    $this->getJson(route('admin.audit-logs.data', $params))->assertOk()->assertJsonCount(2, 'data');
    $this->get(route('admin.audit-logs.show', $logs->last()))->assertOk()
        ->assertViewHas('log', fn ($log) => strlen($log->old_values['text']) === 100000);
});

test('audit data remains forbidden without audit permissions', function () {
    actingAsPlainUser();
    $this->getJson(route('admin.audit-logs.data'))->assertForbidden();
});
