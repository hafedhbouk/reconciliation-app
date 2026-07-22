<?php

namespace App\Policies;

use App\Models\User;

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
