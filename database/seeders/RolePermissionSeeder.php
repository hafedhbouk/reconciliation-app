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
        'imports',
        'matching-rules',
        'matching-results',
        'exceptions',
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
            'imports.viewAny', 'imports.view',
            'matching-rules.viewAny', 'matching-rules.view',
            'matching-results.viewAny', 'matching-results.view',
            'exceptions.viewAny', 'exceptions.view',
        ]);

        $operator = Role::findOrCreate('operator');
        $operator->syncPermissions([
            'imports.viewAny', 'imports.view', 'imports.create',
            'sources.viewAny', 'sources.view', 'sources.update',
            'matching-rules.viewAny', 'matching-rules.view',
            'matching-results.viewAny', 'matching-results.view', 'matching-results.create',
            'exceptions.viewAny', 'exceptions.view', 'exceptions.update',
        ]);
    }
}
