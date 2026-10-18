<?php

namespace App\Policies;

use App\Models\User;

/**
 * Droits sur le journal d'audit : lecture seule.
 * On peut le consulter avec la permission « audit-logs.viewAny / view », mais créer,
 * modifier ou supprimer est refusé à TOUT LE MONDE (return false), administrateurs compris.
 */
class AuditLogPolicy extends BasePolicy
{
    protected string $resource = 'audit-logs';

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function restore(User $user): bool
    {
        return false;
    }
}
