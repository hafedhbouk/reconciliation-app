<?php

use App\Models\Bank;

test('admin role can access admin resources', function () {
    actingAsAdmin();

    $this->get(route('admin.banks.index'))->assertOk();
});

test('super-admin bypasses permission checks entirely', function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $user = \App\Models\User::factory()->create();
    $user->assignRole('super-admin');
    $this->actingAs($user);

    $this->get(route('admin.banks.index'))->assertOk();
    $this->get(route('admin.users.index'))->assertOk();
});

test('user without permissions is denied access to admin resources', function () {
    actingAsPlainUser();

    $this->get(route('admin.banks.index'))->assertForbidden();
});

test('guest is redirected to login when accessing admin resources', function () {
    $this->get(route('admin.banks.index'))->assertRedirect(route('login'));
});

test('user without delete permission cannot delete a bank', function () {
    actingAsPlainUser();

    $bank = Bank::factory()->create();

    $this->delete(route('admin.banks.destroy', $bank))->assertForbidden();
    $this->assertDatabaseHas('banks', ['id' => $bank->id, 'deleted_at' => null]);
});

test('director role can view resources but cannot modify them', function () {
    actingAsDirector();

    // Consultation OK
    $this->get(route('admin.banks.index'))->assertOk();
    $this->get(route('admin.imports.index'))->assertOk();
    $this->get(route('admin.matching-rules.index'))->assertOk();
    $this->get(route('admin.matching-results.index'))->assertOk();
    $this->get(route('admin.exceptions.index'))->assertOk();
    $this->get(route('admin.audit-logs.index'))->assertOk();
    $this->get(route('admin.search.index'))->assertOk();

    // Modification interdite
    $bank = Bank::factory()->create();
    $this->post(route('admin.banks.store', $bank))->assertForbidden();
    $this->delete(route('admin.banks.destroy', $bank))->assertForbidden();
});

test('department head role has full CRUD rights on business resources and users', function () {
    actingAsDepartmentHead();

    // Peut créer/modifier/supprimer des ressources métier
    $this->get(route('admin.banks.index'))->assertOk();
    $this->get(route('admin.banks.create'))->assertOk();
    $this->get(route('admin.users.index'))->assertOk();
    $this->get(route('admin.users.create'))->assertOk();
    $this->get(route('admin.imports.index'))->assertOk();
    $this->get(route('admin.matching-rules.index'))->assertOk();
    $this->get(route('admin.matching-results.index'))->assertOk();
    $this->get(route('admin.exceptions.index'))->assertOk();

    // Peut exécuter les actions de rapprochement
    $this->get(route('admin.reconciliation.index'))->assertOk();

    // Ne peut PAS gérer les rôles/permissions (réservé à l'admin)
    $this->get(route('admin.roles.index'))->assertForbidden();
});

test('execution agent role can perform reconciliations and handle exceptions', function () {
    actingAsExecutionAgent();

    // Peut consulter les rapprochements
    $this->get(route('admin.imports.index'))->assertOk();
    $this->get(route('admin.matching-rules.index'))->assertOk();
    $this->get(route('admin.matching-results.index'))->assertOk();
    $this->get(route('admin.exceptions.index'))->assertOk();

    // Peut faire le rapprochement manuel
    $this->get(route('admin.reconciliation.index'))->assertOk();

    // Peut voir la recherche
    $this->get(route('admin.search.index'))->assertOk();

    // Ne peut PAS gérer les utilisateurs
    $this->get(route('admin.users.index'))->assertForbidden();

    // Ne peut PAS gérer les rôles
    $this->get(route('admin.roles.index'))->assertForbidden();

    // Ne peut PAS modifier le paramétrage (banques, sources, etc.)
    $this->get(route('admin.banks.create'))->assertForbidden();
    $this->get(route('admin.sources.create'))->assertForbidden();
});
