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
        'matching-exports',
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

        // Read-only lookup tool, single ability, no create/update/delete/restore
        // -- same standalone-permission pattern as audit-logs above.
        Permission::findOrCreate('search.viewAny');

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
            'matching-exports.viewAny', 'matching-exports.view',
            'exceptions.viewAny', 'exceptions.view',
            'search.viewAny',
        ]);

        $operator = Role::findOrCreate('operator');
        $operator->syncPermissions([
            'imports.viewAny', 'imports.view', 'imports.create',
            'sources.viewAny', 'sources.view', 'sources.update',
            'matching-rules.viewAny', 'matching-rules.view',
            'matching-results.viewAny', 'matching-results.view', 'matching-results.create',
            'matching-exports.viewAny', 'matching-exports.view',
            'exceptions.viewAny', 'exceptions.view', 'exceptions.update',
            'search.viewAny',
        ]);

        // --- Rôles métier (3 nouveaux rôles) ---

        // Directeur : consultation uniquement (lecture seule sur toutes les
        // ressources + journal d'audit + recherche).
        $director = Role::findOrCreate('directeur');
        $director->syncPermissions([
            'banks.viewAny', 'banks.view',
            'sources.viewAny', 'sources.view',
            'currencies.viewAny', 'currencies.view',
            'holidays.viewAny', 'holidays.view',
            'settings.viewAny', 'settings.view',
            'imports.viewAny', 'imports.view',
            'matching-rules.viewAny', 'matching-rules.view',
            'matching-results.viewAny', 'matching-results.view',
            'matching-exports.viewAny', 'matching-exports.view',
            'exceptions.viewAny', 'exceptions.view',
            'users.viewAny', 'users.view',
            'audit-logs.viewAny', 'audit-logs.view',
            'search.viewAny',
        ]);

        // Chef de département : tous les droits (CRUD complet sur toutes les
        // ressources métier + gestion des utilisateurs), sauf la gestion des
        // rôles/permissions qui reste réservée à l'admin.
        $departmentHead = Role::findOrCreate('chef-departement');
        $departmentHead->syncPermissions([
            'banks.viewAny', 'banks.view', 'banks.create', 'banks.update', 'banks.delete', 'banks.restore',
            'sources.viewAny', 'sources.view', 'sources.create', 'sources.update', 'sources.delete', 'sources.restore',
            'currencies.viewAny', 'currencies.view', 'currencies.create', 'currencies.update', 'currencies.delete', 'currencies.restore',
            'holidays.viewAny', 'holidays.view', 'holidays.create', 'holidays.update', 'holidays.delete', 'holidays.restore',
            'settings.viewAny', 'settings.view', 'settings.create', 'settings.update', 'settings.delete', 'settings.restore',
            'imports.viewAny', 'imports.view', 'imports.create', 'imports.update', 'imports.delete', 'imports.restore',
            'matching-rules.viewAny', 'matching-rules.view', 'matching-rules.create', 'matching-rules.update', 'matching-rules.delete', 'matching-rules.restore',
            'matching-results.viewAny', 'matching-results.view', 'matching-results.create', 'matching-results.update', 'matching-results.delete', 'matching-results.restore',
            'matching-exports.viewAny', 'matching-exports.view', 'matching-exports.create',
            'exceptions.viewAny', 'exceptions.view', 'exceptions.create', 'exceptions.update', 'exceptions.delete', 'exceptions.restore',
            'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete', 'users.restore',
            'audit-logs.viewAny', 'audit-logs.view',
            'search.viewAny',
        ]);

        // Agent d'exécution : rapprochement uniquement (lancer les règles
        // automatiques, faire le rapprochement manuel, traiter les exceptions).
        $executionAgent = Role::findOrCreate('agent-execution');
        $executionAgent->syncPermissions([
            'imports.viewAny', 'imports.view', 'imports.create',
            'sources.viewAny', 'sources.view',
            'matching-rules.viewAny', 'matching-rules.view', 'matching-rules.update',
            'matching-results.viewAny', 'matching-results.view', 'matching-results.create',
            'matching-exports.viewAny', 'matching-exports.view',
            'exceptions.viewAny', 'exceptions.view', 'exceptions.update',
            'search.viewAny',
        ]);
    }
}
