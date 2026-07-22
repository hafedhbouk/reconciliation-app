<?php

use App\Models\Holiday;

test('admin can list holidays', function () {
    actingAsAdmin();
    Holiday::factory()->count(2)->create();

    $this->get(route('admin.holidays.index'))->assertOk();
});

test('admin can create a holiday', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.holidays.store'), [
        'holiday_date' => '2026-12-25',
        'name' => 'Test Holiday',
        'country_code' => 'TN',
        'is_recurring_yearly' => 1,
    ]);

    $response->assertRedirect(route('admin.holidays.index'));
    $this->assertDatabaseHas('holidays', ['name' => 'Test Holiday', 'country_code' => 'TN']);
});

test('duplicate holiday date and country is rejected', function () {
    actingAsAdmin();
    Holiday::factory()->create(['holiday_date' => '2026-01-01', 'country_code' => 'TN']);

    $response = $this->post(route('admin.holidays.store'), [
        'holiday_date' => '2026-01-01',
        'name' => 'New Year Duplicate',
        'country_code' => 'TN',
    ]);

    $response->assertSessionHasErrors('holiday_date');
});

test('admin can soft delete a holiday', function () {
    actingAsAdmin();
    $holiday = Holiday::factory()->create();

    $this->delete(route('admin.holidays.destroy', $holiday))->assertRedirect(route('admin.holidays.index'));

    $this->assertSoftDeleted('holidays', ['id' => $holiday->id]);
});
