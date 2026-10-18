<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Gestion des rôles et de leurs permissions (Administration > Rôles).
 *
 * Rôles et permissions viennent du paquet spatie/laravel-permission :
 * un rôle (ex. « admin ») regroupe des permissions (ex. « imports.create »).
 * Même structure CRUD que BankController.
 */
class RoleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Role::class, 'role');
    }

    public function index(): View
    {
        $roles = Role::query()->withCount('permissions')->orderBy('name')->paginate(15);

        return view('admin.roles.index', compact('roles'));
    }

    public function create(): View
    {
        return view('admin.roles.create', ['permissions' => $this->groupedPermissions()]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->validated('name'), 'guard_name' => 'web']);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', __('Rôle créé avec succès.'));
    }

    public function edit(Role $role): View
    {
        return view('admin.roles.edit', ['role' => $role, 'permissions' => $this->groupedPermissions()]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($request->validated('permissions', []));

        return redirect()->route('admin.roles.index')->with('status', __('Rôle mis à jour avec succès.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        // Les rôles système sont protégés : les supprimer bloquerait l'administration.
        if (in_array($role->name, ['super-admin', 'admin'], true)) {
            return redirect()->route('admin.roles.index')->with('status', __('Ce rôle système ne peut pas être supprimé.'));
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', __('Rôle supprimé avec succès.'));
    }

    /**
     * Permissions regroupées par module pour l'affichage en cases à cocher :
     * 'imports.create' et 'imports.delete' vont dans le groupe 'imports' (le texte avant le point).
     */
    private function groupedPermissions(): Collection
    {
        return Permission::query()->orderBy('name')->get()->groupBy(function (Permission $permission) {
            return explode('.', $permission->name)[0];
        });
    }
}
