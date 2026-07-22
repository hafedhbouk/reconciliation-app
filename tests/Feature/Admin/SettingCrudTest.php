<?php

use App\Models\Setting;

test('admin can view settings grouped by group', function () {
    actingAsAdmin();
    Setting::factory()->create(['group' => 'matching', 'key' => 'tolerance_days']);

    $this->get(route('admin.settings.index'))->assertOk();
});

test('admin can update a setting value', function () {
    actingAsAdmin();
    $setting = Setting::factory()->create(['type' => 'string', 'value' => 'old-value']);

    $response = $this->put(route('admin.settings.update', $setting), [
        'value' => 'new-value',
    ]);

    $response->assertRedirect(route('admin.settings.index'));
    expect($setting->fresh()->value)->toBe('new-value');
});

test('integer settings are cast correctly on update', function () {
    actingAsAdmin();
    $setting = Setting::factory()->create(['type' => 'integer', 'value' => 5]);

    $this->put(route('admin.settings.update', $setting), ['value' => '42']);

    expect($setting->fresh()->value)->toBe(42);
});
