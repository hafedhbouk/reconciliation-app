<?php

use App\Models\MatchingRule;

test('stored rules lose the alphanumeric exclusion and keep their other criteria', function () {
    $criteria = [
        'tolerance_amount_millimes' => 0,
        'tolerance_days' => 0,
        'excluded_status_raw' => ['a' => [], 'b' => ['Commission']],
        'primary_key' => ['a' => 'num_autorisation', 'b' => 'num_autorisation'],
        'verify_fields' => ['amount', 'date'],
    ];
    $legacy = MatchingRule::factory()->create(['criteria' => $criteria + [
        'excluded_non_numeric' => ['a' => ['num_autorisation'], 'b' => ['num_autorisation']],
    ]]);
    $current = MatchingRule::factory()->create(['criteria' => $criteria]);

    (require database_path('migrations/2026_10_18_000001_remove_excluded_non_numeric_from_matching_rules.php'))->up();

    // JSON storage may reorder keys; only the content must match.
    expect($legacy->fresh()->criteria)->toEqual($criteria)
        ->and($current->fresh()->criteria)->toEqual($criteria);
});
