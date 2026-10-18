<?php

namespace App\Policies;

/**
 * Droits sur les utilisateurs : permissions « users.* » (voir BasePolicy).
 */
class UserPolicy extends BasePolicy
{
    protected string $resource = 'users';
}
