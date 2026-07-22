<?php

use App\Models\Currency;

test('admin can list currencies', function () {
    actingAsAdmin();
    Currency::factory()->count(2)->create();

    $this->get(route('admin.currencies.index'))->assertOk();
});

test('admin can create a currency', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.currencies.store'), [
        'iso_code' => 'XYZ',
        'name' => 'Test Currency',
        'decimal_places' => 3,
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('admin.currencies.index'));
    $this->assertDatabaseHas('currencies', ['iso_code' => 'XYZ']);
});

test('admin can update a currency', function () {
    actingAsAdmin();
    $currency = Currency::factory()->create();

    $response = $this->put(route('admin.currencies.update', $currency), [
        'iso_code' => $currency->iso_code,
        'name' => 'Updated Currency Name',
        'decimal_places' => 2,
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('admin.currencies.index'));
    $this->assertDatabaseHas('currencies', ['id' => $currency->id, 'name' => 'Updated Currency Name']);
});

test('admin can soft delete a currency', function () {
    actingAsAdmin();
    $currency = Currency::factory()->create();

    $this->delete(route('admin.currencies.destroy', $currency))->assertRedirect(route('admin.currencies.index'));

    $this->assertSoftDeleted('currencies', ['id' => $currency->id]);
});
