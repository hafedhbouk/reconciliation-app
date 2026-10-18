<?php

namespace App\Policies;

/**
 * Droits sur les paramètres : permissions « settings.* » (voir BasePolicy).
 */
class SettingPolicy extends BasePolicy
{
    protected string $resource = 'settings';
}
