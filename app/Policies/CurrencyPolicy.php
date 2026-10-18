<?php

namespace App\Policies;

/**
 * Droits sur les devises : permissions « currencies.* » (voir BasePolicy).
 */
class CurrencyPolicy extends BasePolicy
{
    protected string $resource = 'currencies';
}
