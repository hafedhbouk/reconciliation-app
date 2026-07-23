<?php

namespace App\DataTransferObjects;

readonly class DuplicateScanSummary
{
    public function __construct(
        public int $groupsFound,
        public int $exceptionsCreated,
    ) {
    }
}
