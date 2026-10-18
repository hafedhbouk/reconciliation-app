<?php

namespace App\Policies;

/**
 * Droits sur les exceptions : permissions « exceptions.* » (voir BasePolicy).
 */
class ExceptionRecordPolicy extends BasePolicy
{
    protected string $resource = 'exceptions';
}
