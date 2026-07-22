<?php

use App\Models\User;

test('admin can view the users index page', function () {
    actingAsAdmin();

    $this->get(route('admin.users.index'))->assertOk();
});

test('admin can fetch users via the datatables ajax endpoint', function () {
    actingAsAdmin();
    User::factory()->count(2)->create();

    $response = $this->getJson(route('admin.users.data'));

    $response->assertOk();
    $response->assertJsonPath('recordsTotal', 3); // 2 created + the acting admin
});

test('admin can create a user with a role', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.users.store'), [
        'name' => 'New User',
        'email' => 'new.user@example.com',
        'password' => 'password123',
        'is_active' => 1,
        'roles' => ['auditor'],
    ]);

    $response->assertRedirect(route('admin.users.index'));
    $user = User::query()->where('email', 'new.user@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('auditor'))->toBeTrue();
});

test('admin can update a user without changing password', function () {
    $admin = actingAsAdmin();
    $user = User::factory()->create(['name' => 'Old Name']);

    $response = $this->put(route('admin.users.update', $user), [
        'name' => 'New Name',
        'email' => $user->email,
        'is_active' => 1,
    ]);

    $response->assertRedirect(route('admin.users.index'));
    expect($user->fresh()->name)->toBe('New Name');
    expect($admin->id)->not->toBe($user->id);
});

test('user cannot delete their own account from the admin panel', function () {
    $admin = actingAsAdmin();

    $this->delete(route('admin.users.destroy', $admin));

    $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
});

test('admin can soft delete another user', function () {
    actingAsAdmin();
    $user = User::factory()->create();

    $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('admin.users.index'));

    $this->assertSoftDeleted('users', ['id' => $user->id]);
});
