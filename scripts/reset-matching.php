<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Enums\MatchingStatus;
use App\Models\ExceptionRecord;
use App\Models\MatchingDetail;
use App\Models\MatchingResult;
use App\Models\NormalizedTransaction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

echo "Starting cleanup...\n";

$start = microtime(true);

$resultsCount = MatchingResult::count();
$detailsCount = MatchingDetail::count();
$exceptionsCount = ExceptionRecord::count();

echo "Found {$resultsCount} matching results\n";
echo "Found {$detailsCount} matching details\n";
echo "Found {$exceptionsCount} exceptions\n";

DB::transaction(function () use ($resultsCount, $detailsCount, $exceptionsCount) {
    if ($exceptionsCount > 0) {
        ExceptionRecord::query()->limit($exceptionsCount)->delete();
        echo "Deleted exceptions\n";
    }

    if ($detailsCount > 0) {
        MatchingDetail::query()->limit($detailsCount)->delete();
        echo "Deleted matching details\n";
    }

    if ($resultsCount > 0) {
        MatchingResult::query()->delete();
        echo "Deleted matching results\n";
    }
});

$normalizedIds = NormalizedTransaction::query()
    ->where('matching_status', '!=', MatchingStatus::Unmatched->value)
    ->pluck('id');

echo 'Resetting '.$normalizedIds->count()." normalized transactions...\n";

$normalizedIds->chunk(5000)->each(function ($ids) {
    NormalizedTransaction::query()
        ->whereIn('id', $ids)
        ->update(['matching_status' => MatchingStatus::Unmatched->value]);
});

$duration = round(microtime(true) - $start, 2);

echo "\nDone in {$duration}s\n";

$resultsAfter = MatchingResult::count();
$detailsAfter = MatchingDetail::count();
$exceptionsAfter = ExceptionRecord::count();
$unmatchedAfter = NormalizedTransaction::where('matching_status', MatchingStatus::Unmatched->value)->count();

echo "\nAfter cleanup:\n";
echo "Matching results: {$resultsAfter}\n";
echo "Matching details: {$detailsAfter}\n";
echo "Exceptions: {$exceptionsAfter}\n";
echo "Unmatched normalized transactions: {$unmatchedAfter}\n";
