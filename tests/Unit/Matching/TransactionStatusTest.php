<?php

use App\Enums\ExceptionStatus;
use App\Enums\ExceptionType;
use App\Enums\MatchingResultStatus;
use App\Enums\MatchingStatus;
use App\Models\ExceptionRecord;
use App\Models\MatchingResult;
use App\Models\NormalizedTransaction;
use App\Services\Matching\TransactionStatus;

test('matching resolves open unmatched exceptions but leaves exceptions in review untouched', function () {
    $row = NormalizedTransaction::factory()->create(['matching_status' => MatchingStatus::Unmatched->value]);
    $open = ExceptionRecord::factory()->create([
        'normalized_transaction_id' => $row->id,
        'type' => ExceptionType::Unmatched->value,
        'status' => ExceptionStatus::Open->value,
    ]);
    $inReview = ExceptionRecord::factory()->create([
        'normalized_transaction_id' => $row->id,
        'type' => ExceptionType::Unmatched->value,
        'status' => ExceptionStatus::InReview->value,
    ]);
    $result = MatchingResult::factory()->create(['status' => MatchingResultStatus::Matched->value]);
    $result->matchingDetails()->create([
        'normalized_transaction_id' => $row->id,
        'side' => 'a',
    ]);

    app(TransactionStatus::class)->refresh(collect([$row->id]));

    expect($row->fresh()->matching_status)->toBe(MatchingStatus::Matched)
        ->and($open->fresh()->status)->toBe(ExceptionStatus::Resolved)
        ->and($open->fresh()->resolved_at)->not->toBeNull()
        ->and($open->fresh()->resolution_comment)->toContain('automatiquement')
        ->and($inReview->fresh()->status)->toBe(ExceptionStatus::InReview);
});