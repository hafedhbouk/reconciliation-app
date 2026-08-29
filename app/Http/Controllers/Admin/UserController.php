<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(): View
    {
        return view('admin.users.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with('roles')->select('users.*');

        return DataTables::of($users)
            ->addColumn('roles', fn (User $user) => $user->roles->pluck('name')->implode(', '))
            ->addColumn('status', fn (User $user) => $user->is_active ? __('Actif') : __('Inactif'))
            ->addColumn('actions', fn (User $user) => view('admin.users._actions', ['user' => $user])->render())
            ->rawColumns(['actions'])
            ->toJson();
    }

    public function create(): View
    {
        return view('admin.users.create', ['roles' => Role::query()->orderBy('name')->get()]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create([
            'prenom' => $request->validated('prenom'),
            'nom' => $request->validated('nom'),
            'name' => $request->validated('name'),
            'matricule' => $request->validated('matricule'),
            'portable' => $request->validated('portable'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'is_active' => $request->boolean('is_active', true),
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($request->validated('roles', []));

        return redirect()->route('admin.users.index')->with('status', __('Utilisateur créé avec succès.'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user, 'roles' => Role::query()->orderBy('name')->get()]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'prenom' => $request->validated('prenom'),
            'nom' => $request->validated('nom'),
            'name' => $request->validated('name'),
            'matricule' => $request->validated('matricule'),
            'portable' => $request->validated('portable'),
            'email' => $request->validated('email'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->validated('password'));
        }

        $user->update($data);
        $user->syncRoles($request->validated('roles', []));

        return redirect()->route('admin.users.index')->with('status', __('Utilisateur mis à jour avec succès.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.users.index')->with('status', __('Vous ne pouvez pas supprimer votre propre compte.'));
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', __('Utilisateur supprimé avec succès.'));
    }
}
