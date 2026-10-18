<?php

namespace App\Policies;

/**
 * Droits sur les jours fériés : permissions « holidays.* » (voir BasePolicy).
 */
class HolidayPolicy extends BasePolicy
{
    protected string $resource = 'holidays';
}
