<?php

use App\Models\Bank;
use App\Models\User;

test('created_by is set automatically from the authenticated user', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $bank = Bank::factory()->create();

    expect($bank->created_by)->toBe($user->id);
});

test('updated_by is set automatically when a model is updated', function () {
    $creator = User::factory()->create();
    $this->actingAs($creator);
    $bank = Bank::factory()->create();

    $editor = User::factory()->create();
    $this->actingAs($editor);
    $bank->update(['name' => 'Changed by editor']);

    expect($bank->fresh()->updated_by)->toBe($editor->id);
    expect($bank->fresh()->created_by)->toBe($creator->id);
});

test('created_by is null when there is no authenticated user', function () {
    $bank = Bank::factory()->create();

    expect($bank->created_by)->toBeNull();
});
