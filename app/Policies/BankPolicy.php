<?php

namespace App\Policies;

/**
 * Droits sur les banques : permissions « banks.* » (voir BasePolicy).
 */
class BankPolicy extends BasePolicy
{
    protected string $resource = 'banks';
}
