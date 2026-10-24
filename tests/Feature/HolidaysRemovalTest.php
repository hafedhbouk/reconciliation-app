<?php

use App\Models\Holiday;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Migration de retrait des permissions holidays.* (Phase 5). */
function holidaysMigration(): Migration
{
    return require database_path('migrations/2026_10_24_000002_remove_holidays_permissions.php');
}

// Seules les sauvegardes créées par le test sont effacées (jamais celle de la vraie migration).
beforeEach(function () {
    $this->existingBackups = glob(storage_path('app/holidays-permissions-*.json')) ?: [];
});

afterEach(function () {
    array_map('unlink', array_diff(glob(storage_path('app/holidays-permissions-*.json')) ?: [], $this->existingBackups));
});

test('holidays no longer appear in the role administration', function () {
    actingAsAdmin();

    $this->get(route('admin.roles.create'))->assertOk()
        ->assertSee('banks.viewAny')->assertDontSee('holidays');
    $this->get(route('admin.roles.edit', Role::findByName('directeur')))->assertOk()->assertDontSee('holidays');
    expect(Permission::query()->where('name', 'like', 'holidays.%')->count())->toBe(0);
});

test('the holidays screens are gone while the other administration screens still work', function () {
    actingAsAdmin();

    $this->get('/admin/holidays')->assertNotFound();
    $this->post('/admin/holidays', ['holiday_date' => '2026-12-25', 'name' => 'Test'])->assertNotFound();
    foreach (['admin.banks.index', 'admin.sources.index', 'admin.currencies.index', 'admin.settings.index', 'admin.roles.index', 'admin.users.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('removing the permissions keeps every other permission and a backup, and can be undone', function () {
    $this->seed(RolePermissionSeeder::class);
    // État d'avant la Phase 5 : permissions holidays.* données à des rôles et à un utilisateur.
    $abilities = ['viewAny', 'view', 'create', 'update', 'delete', 'restore'];
    foreach ($abilities as $ability) {
        Permission::findOrCreate("holidays.{$ability}");
    }
    Role::findByName('admin')->givePermissionTo(array_map(fn ($a) => "holidays.{$a}", $abilities));
    Role::findByName('directeur')->givePermissionTo(['holidays.viewAny', 'holidays.view']);
    $user = User::factory()->create();
    $user->givePermissionTo('holidays.view');
    $others = fn () => DB::table('role_has_permissions')->join('permissions', 'permissions.id', '=', 'permission_id')
        ->where('name', 'not like', 'holidays.%')->orderBy('role_id')->orderBy('name')->get(['role_id', 'name'])->map(fn ($row) => "{$row->role_id}:{$row->name}")->all();
    $before = $others();

    holidaysMigration()->up();

    expect(Permission::query()->where('name', 'like', 'holidays.%')->count())->toBe(0)
        ->and($others())->toBe($before) // autres permissions intactes
        ->and($user->fresh()->getAllPermissions()->pluck('name')->all())->toBe([]);
    $backups = array_values(array_diff(glob(storage_path('app/holidays-permissions-*.json')), $this->existingBackups));
    expect($backups)->toHaveCount(1);
    $snapshot = json_decode(file_get_contents($backups[0]), true);
    expect($snapshot['permissions'])->toHaveCount(6)->and($snapshot['roles'])->toHaveCount(8)->and($snapshot['models'])->toHaveCount(1);

    holidaysMigration()->down();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(Role::findByName('admin')->fresh()->hasPermissionTo('holidays.restore'))->toBeTrue()
        ->and(Role::findByName('directeur')->fresh()->getPermissionNames()->filter(fn ($n) => str_starts_with($n, 'holidays.'))->sort()->values()->all())
        ->toBe(['holidays.view', 'holidays.viewAny'])
        ->and($user->fresh()->hasDirectPermission('holidays.view'))->toBeTrue()
        ->and($others())->toBe($before);
});

test('historical holidays data is kept', function () {
    $holiday = Holiday::query()->create(['holiday_date' => '2026-03-20', 'name' => 'Fête de l’indépendance', 'country_code' => 'TN', 'is_recurring_yearly' => true]);

    holidaysMigration()->up();

    expect(Holiday::query()->find($holiday->id))->not->toBeNull()->name->toBe('Fête de l’indépendance');
});
