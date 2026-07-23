<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Matching chunk size
    |--------------------------------------------------------------------------
    |
    | Used by DuplicateDetector/UnmatchedSweeper's bulk-insert chunking only.
    | RuleMatcher doesn't chunk — it loads one source's unmatched pool per
    | side and groups in PHP (a few MB at real volumes), since the 3-way
    | amount/date tolerance branch isn't expressible as a single SQL predicate.
    |
    */
    'chunk_size' => env('MATCHING_CHUNK_SIZE', 1000),
];
