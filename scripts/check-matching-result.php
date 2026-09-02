<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Simulate the controller logic for one matching result
$result = App\Models\MatchingResult::with([
    'matchingRule.sourceA',
    'matchingRule.sourceB',
    'matchedByUser',
    'matchingDetails.normalizedTransaction.transaction.source',
    'exceptions',
])->whereNotNull('matching_rule_id')->first();

if (!$result) {
    echo "No matching results with rule found.\n";
    exit(1);
}

$sourceAId = $result->matchingRule->source_a_id;
$sourceBId = $result->matchingRule->source_b_id;

$matchedIds = $result->matchingDetails
    ->pluck('normalized_transaction_id')
    ->filter()
    ->unique()
    ->values();

$unmatchedA = App\Models\NormalizedTransaction::query()
    ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
    ->where('transactions.source_id', $sourceAId)
    ->where('normalized_transactions.matching_status', 'unmatched')
    ->when($matchedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('normalized_transactions.id', $matchedIds))
    ->select('normalized_transactions.*', 'transactions.raw_payload')
    ->count();

$unmatchedB = App\Models\NormalizedTransaction::query()
    ->join('transactions', 'transactions.id', '=', 'normalized_transactions.transaction_id')
    ->where('transactions.source_id', $sourceBId)
    ->where('normalized_transactions.matching_status', 'unmatched')
    ->when($matchedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('normalized_transactions.id', $matchedIds))
    ->select('normalized_transactions.*', 'transactions.raw_payload')
    ->count();

echo "Result ID: " . $result->id . "\n";
echo "Rule: " . ($result->matchingRule?->name ?? 'manual') . "\n";
echo "Status: " . $result->status->value . "\n";
echo "Source A ID: $sourceAId\n";
echo "Source B ID: $sourceBId\n";
echo "Matched IDs count: " . $matchedIds->count() . "\n";
echo "Unmatched A count: $unmatchedA\n";
echo "Unmatched B count: $unmatchedB\n";
echo "Matched details count: " . $result->matchingDetails->count() . "\n";
echo "Exceptions count: " . $result->exceptions->count() . "\n";
