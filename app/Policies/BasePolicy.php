<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{
    /**
     * Permission prefix this policy checks against, e.g. "banks" for "banks.viewAny".
     */
    protected string $resource;

    public function viewAny(User $user): bool
    {
        return $user->can("{$this->resource}.viewAny");
    }

    public function view(User $user): bool
    {
        return $user->can("{$this->resource}.view");
    }

    public function create(User $user): bool
    {
        return $user->can("{$this->resource}.create");
    }

    public function update(User $user): bool
    {
        return $user->can("{$this->resource}.update");
    }

    public function delete(User $user): bool
    {
        return $user->can("{$this->resource}.delete");
    }

    public function restore(User $user): bool
    {
        return $user->can("{$this->resource}.restore");
    }
}
