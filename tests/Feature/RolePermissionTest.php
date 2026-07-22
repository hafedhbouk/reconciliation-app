<?php

use App\Models\Bank;

test('admin role can access admin resources', function () {
    actingAsAdmin();

    $this->get(route('admin.banks.index'))->assertOk();
});

test('super-admin bypasses permission checks entirely', function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $user = \App\Models\User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);

    $this->get(route('admin.banks.index'))->assertOk();
    $this->get(route('admin.users.index'))->assertOk();
});

test('user without permissions is denied access to admin resources', function () {
    actingAsPlainUser();

    $this->get(route('admin.banks.index'))->assertForbidden();
});

test('guest is redirected to login when accessing admin resources', function () {
    $this->get(route('admin.banks.index'))->assertRedirect(route('login'));
});

test('user without delete permission cannot delete a bank', function () {
    actingAsPlainUser();

    $bank = Bank::factory()->create();

    $this->delete(route('admin.banks.destroy', $bank))->assertForbidden();
    $this->assertDatabaseHas('banks', ['id' => $bank->id, 'deleted_at' => null]);
});
