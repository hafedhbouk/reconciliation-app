<?php

use App\Models\User;
use App\Notifications\ImportProcessedNotification;
use App\Models\Import;

test('the notifications index page renders and lists the user own notifications', function () {
    $user = User::factory()->create();
    $import = Import::factory()->create();
    $user->notify(new ImportProcessedNotification($import));

    $this->actingAs($user)->get(route('notifications.index'))->assertOk();
});

test('a user can mark their own notification as read', function () {
    $user = User::factory()->create();
    $import = Import::factory()->create();
    $user->notify(new ImportProcessedNotification($import));
    $notification = $user->notifications()->sole();

    expect($notification->read_at)->toBeNull();

    $this->actingAs($user)->post(route('notifications.read', $notification))->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another user notification as read', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $import = Import::factory()->create();
    $owner->notify(new ImportProcessedNotification($import));
    $notification = $owner->notifications()->sole();

    $this->actingAs($other)->post(route('notifications.read', $notification))->assertForbidden();
    expect($notification->fresh()->read_at)->toBeNull();
});

test('markAllAsRead clears the unread count', function () {
    $user = User::factory()->create();
    $import = Import::factory()->create();
    $user->notify(new ImportProcessedNotification($import));
    $user->notify(new ImportProcessedNotification($import));

    expect($user->unreadNotifications()->count())->toBe(2);

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('guest is redirected to login when accessing notifications', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
});
