<?php

namespace App\Policies;

/**
 * Politique de base abstraite pour toutes les ressources.
 *
 * Chaque policy concrète définit un préfixe de permission (ex:
 * "imports") et hérite des méthodes viewAny, view, create, update,
 * delete, restore qui vérifient la permission correspondante
 * (ex: "imports.update").
 *
 * ---------------------------------------------------------------------------
 * GUIDE DÉBUTANT : qu'est-ce qu'une « Policy » ? (valable pour app/Policies)
 * ---------------------------------------------------------------------------
 * Une Policy répond à la question « cet utilisateur a-t-il le droit de faire
 * cette action sur ce type de donnée ? ». Chaque méthode renvoie true (oui) ou false (non).
 *   - Laravel associe automatiquement ImportPolicy au modèle Import (par le nom).
 *   - Dans un contrôleur : $this->authorize('update', $import) -> erreur 403 si false.
 *   - Dans une vue Blade : @can('imports.create') ... @endcan pour masquer un bouton.
 * Les permissions (« imports.create »…) et les rôles qui les regroupent sont
 * créés par database/seeders/RolePermissionSeeder.php et modifiables dans
 * Administration > Rôles. Le rôle super-admin a tous les droits
 * (voir AppServiceProvider).
 */
use App\Models\User;

abstract class BasePolicy
{
    /**
     * Permission prefix this policy checks against, e.g. "banks" for "banks.viewAny".
     * (Chaque Policy enfant remplit cette valeur.)
     */
    protected string $resource;

    /** Voir la liste. */
    public function viewAny(User $user): bool
    {
        // $user->can('banks.viewAny') : vrai si l'un des rôles de l'utilisateur possède cette permission.
        return $user->can("{$this->resource}.viewAny");
    }

    /** Voir le détail d'un élément. */
    public function view(User $user): bool
    {
        return $user->can("{$this->resource}.view");
    }

    /** Créer un élément. */
    public function create(User $user): bool
    {
        return $user->can("{$this->resource}.create");
    }

    /** Modifier un élément. */
    public function update(User $user): bool
    {
        return $user->can("{$this->resource}.update");
    }

    /** Supprimer un élément. */
    public function delete(User $user): bool
    {
        return $user->can("{$this->resource}.delete");
    }

    /** Restaurer un élément supprimé (soft delete). */
    public function restore(User $user): bool
    {
        return $user->can("{$this->resource}.restore");
    }
}
