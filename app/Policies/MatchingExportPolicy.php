<?php

namespace App\Policies;

use App\Models\MatchingExport;
use App\Models\User;

class MatchingExportPolicy extends BasePolicy
{
    protected string $resource = 'matching-exports';
}
