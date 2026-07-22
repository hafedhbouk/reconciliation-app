<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    private const RESOURCES = [
        'banks',
        'sources',
        'currencies',
        'holidays',
        'settings',
        'users',
        'roles',
    ];

    private const ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete', 'restore'];

    public function run(): void
    {
        foreach (self::RESOURCES as $resource) {
            foreach (self::ABILITIES as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}");
            }
        }

        Permission::findOrCreate('audit-logs.viewAny');
        Permission::findOrCreate('audit-logs.view');

        Role::findOrCreate('super-admin');

        $admin = Role::findOrCreate('admin');
        $admin->syncPermissions(Permission::all());

        $auditor = Role::findOrCreate('auditor');
        $auditor->syncPermissions([
            'audit-logs.viewAny',
            'audit-logs.view',
            'banks.viewAny', 'banks.view',
            'sources.viewAny', 'sources.view',
            'currencies.viewAny', 'currencies.view',
            'holidays.viewAny', 'holidays.view',
            'settings.viewAny', 'settings.view',
        ]);

        // Placeholder for Phase 2/3 import/matching permissions.
        Role::findOrCreate('operator');
    }
}
