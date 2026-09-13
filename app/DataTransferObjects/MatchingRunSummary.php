<?php

namespace App\DataTransferObjects;

readonly class MatchingRunSummary
{
    public function __construct(
        public int $referencesConsidered,
        public int $matched,
        public int $conflicts,
        public int $noSignal,
        public int $skipped = 0,
        public int $unmatchedA = 0,
        public int $unmatchedB = 0,
    ) {}
}
