<?php

namespace App\Policies;

use App\Models\Operation;
use App\Models\User;

/**
 * Accès au suivi des opérations : chacun voit ses propres opérations, les
 * administrateurs les voient toutes (le super-admin passe déjà par
 * Gate::before dans AppServiceProvider).
 * (Les notions de Policy sont expliquées dans app/Policies/ImportPolicy.php.)
 */
class OperationPolicy
{
    /** Voir toutes les opérations, pas seulement les siennes. */
    public function viewAll(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, Operation $operation): bool
    {
        return $operation->user_id === $user->id || $this->viewAll($user);
    }
}
