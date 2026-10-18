<?php

namespace App\Policies;

/**
 * Droits sur les rôles : permissions « roles.* » (voir BasePolicy).
 */
class RolePolicy extends BasePolicy
{
    protected string $resource = 'roles';
}
