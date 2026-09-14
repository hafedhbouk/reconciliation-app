<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('a fresh installation creates the documented administrator and permits login', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@reconciliation.local')->sole();
    expect(Hash::check('password', $admin->password))->toBeTrue();
    expect($admin->hasRole('super-admin'))->toBeTrue();
    expect($admin->hasVerifiedEmail())->toBeTrue();
    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($admin);
    $this->get(route('dashboard'))->assertOk();
});

test('running installation seeds again preserves an administrators changed password', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', 'admin@reconciliation.local')->sole();
    $admin->update(['password' => 'Changed!Password42']);
    $this->seed(DatabaseSeeder::class);
    expect(User::where('email', $admin->email)->count())->toBe(1);
    expect(Hash::check('Changed!Password42', $admin->fresh()->password))->toBeTrue();
    expect(Hash::check('password', $admin->fresh()->password))->toBeFalse();
});
