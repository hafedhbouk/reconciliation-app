<?php

namespace App\Policies;

/**
 * Droits sur les sources (et leur mapping) : permissions « sources.* » (voir BasePolicy).
 */
class SourcePolicy extends BasePolicy
{
    protected string $resource = 'sources';
}
