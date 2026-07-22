<?php

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\User;

test('creating a bank writes an audit log entry with correct new values', function () {
    $admin = actingAsAdmin();

    $this->post(route('admin.banks.store'), [
        'code' => 'AUDITME',
        'name' => 'Audited Bank',
        'is_active' => 1,
    ]);

    $bank = Bank::query()->where('code', 'AUDITME')->first();

    $log = AuditLog::query()
        ->where('auditable_type', Bank::class)
        ->where('auditable_id', $bank->id)
        ->where('event', 'created')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->user_id)->toBe($admin->id);
    expect($log->new_values['code'])->toBe('AUDITME');
});

test('updating a bank writes an audit log entry with old and new values', function () {
    actingAsAdmin();
    $bank = Bank::factory()->create(['name' => 'Original Name']);

    $this->put(route('admin.banks.update', $bank), [
        'code' => $bank->code,
        'name' => 'Changed Name',
        'is_active' => 1,
    ]);

    $log = AuditLog::query()
        ->where('auditable_type', Bank::class)
        ->where('auditable_id', $bank->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->old_values['name'])->toBe('Original Name');
    expect($log->new_values['name'])->toBe('Changed Name');
});

test('deleting a bank writes an audit log entry', function () {
    actingAsAdmin();
    $bank = Bank::factory()->create();

    $this->delete(route('admin.banks.destroy', $bank));

    $log = AuditLog::query()
        ->where('auditable_type', Bank::class)
        ->where('auditable_id', $bank->id)
        ->where('event', 'deleted')
        ->first();

    expect($log)->not->toBeNull();
});

test('a successful login writes a login audit log entry', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $log = AuditLog::query()->where('event', 'login')->where('user_id', $user->id)->first();

    expect($log)->not->toBeNull();
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('a failed login writes a login_failed audit log entry', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $log = AuditLog::query()->where('event', 'login_failed')->first();

    expect($log)->not->toBeNull();
});
