<?php

use Spatie\Permission\Models\Role;

test('admin can view roles index', function () {
    actingAsAdmin();

    $this->get(route('admin.roles.index'))->assertOk();
});

test('admin can create a role with permissions', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.roles.store'), [
        'name' => 'reviewer',
        'permissions' => ['banks.viewAny', 'banks.view'],
    ]);

    $response->assertRedirect(route('admin.roles.index'));
    $role = Role::query()->where('name', 'reviewer')->first();
    expect($role)->not->toBeNull();
    expect($role->hasPermissionTo('banks.viewAny'))->toBeTrue();
});

test('system roles cannot be deleted', function () {
    actingAsAdmin();
    $adminRole = Role::query()->where('name', 'admin')->first();

    $this->delete(route('admin.roles.destroy', $adminRole))->assertRedirect(route('admin.roles.index'));

    $this->assertDatabaseHas('roles', ['id' => $adminRole->id]);
});

test('a custom role can be deleted', function () {
    actingAsAdmin();
    $role = Role::create(['name' => 'temporary', 'guard_name' => 'web']);

    $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

    $this->assertDatabaseMissing('roles', ['id' => $role->id]);
});
