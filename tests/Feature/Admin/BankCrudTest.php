<?php

use App\Models\Bank;

test('admin can list banks', function () {
    actingAsAdmin();
    Bank::factory()->count(3)->create();

    $this->get(route('admin.banks.index'))->assertOk();
});

test('admin can create a bank', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.banks.store'), [
        'code' => 'TESTBANK',
        'name' => 'Test Bank',
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('admin.banks.index'));
    $this->assertDatabaseHas('banks', ['code' => 'TESTBANK', 'name' => 'Test Bank']);
});

test('bank code must be unique on create', function () {
    actingAsAdmin();
    Bank::factory()->create(['code' => 'DUPE']);

    $response = $this->post(route('admin.banks.store'), [
        'code' => 'DUPE',
        'name' => 'Another Bank',
        'is_active' => 1,
    ]);

    $response->assertSessionHasErrors('code');
});

test('admin can update a bank', function () {
    actingAsAdmin();
    $bank = Bank::factory()->create();

    $response = $this->put(route('admin.banks.update', $bank), [
        'code' => $bank->code,
        'name' => 'Updated Name',
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('admin.banks.index'));
    $this->assertDatabaseHas('banks', ['id' => $bank->id, 'name' => 'Updated Name']);
});

test('admin can soft delete a bank', function () {
    actingAsAdmin();
    $bank = Bank::factory()->create();

    $this->delete(route('admin.banks.destroy', $bank))->assertRedirect(route('admin.banks.index'));

    $this->assertSoftDeleted('banks', ['id' => $bank->id]);
});

test('plain user is forbidden from creating a bank', function () {
    actingAsPlainUser();

    $this->post(route('admin.banks.store'), [
        'code' => 'FORBID',
        'name' => 'Forbidden Bank',
    ])->assertForbidden();
});
