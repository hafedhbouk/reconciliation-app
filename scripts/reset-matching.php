<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Enums\MatchingStatus;

echo "Starting cleanup...\n";

$start = microtime(true);

$resultsCount = App\Models\MatchingResult::count();
$detailsCount = App\Models\MatchingDetail::count();
$exceptionsCount = App\Models\ExceptionRecord::count();

echo "Found {$resultsCount} matching results\n";
echo "Found {$detailsCount} matching details\n";
echo "Found {$exceptionsCount} exceptions\n";

DB::transaction(function () use ($resultsCount, $detailsCount, $exceptionsCount) {
    if ($exceptionsCount > 0) {
        App\Models\ExceptionRecord::query()->limit($exceptionsCount)->delete();
        echo "Deleted exceptions\n";
    }

    if ($detailsCount > 0) {
        App\Models\MatchingDetail::query()->limit($detailsCount)->delete();
        echo "Deleted matching details\n";
    }

    if ($resultsCount > 0) {
        App\Models\MatchingResult::query()->delete();
        echo "Deleted matching results\n";
    }
});

$normalizedIds = App\Models\NormalizedTransaction::query()
    ->where('matching_status', '!=', MatchingStatus::Unmatched->value)
    ->pluck('id');

echo "Resetting " . $normalizedIds->count() . " normalized transactions...\n";

$normalizedIds->chunk(5000)->each(function ($ids) {
    App\Models\NormalizedTransaction::query()
        ->whereIn('id', $ids)
        ->update(['matching_status' => MatchingStatus::Unmatched->value]);
});

$duration = round(microtime(true) - $start, 2);

echo "\nDone in {$duration}s\n";

$resultsAfter = App\Models\MatchingResult::count();
$detailsAfter = App\Models\MatchingDetail::count();
$exceptionsAfter = App\Models\ExceptionRecord::count();
$unmatchedAfter = App\Models\NormalizedTransaction::where('matching_status', MatchingStatus::Unmatched->value)->count();

echo "\nAfter cleanup:\n";
echo "Matching results: {$resultsAfter}\n";
echo "Matching details: {$detailsAfter}\n";
echo "Exceptions: {$exceptionsAfter}\n";
echo "Unmatched normalized transactions: {$unmatchedAfter}\n";
