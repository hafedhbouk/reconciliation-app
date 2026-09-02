<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$duplicates = App\Models\MatchingRule::query()
    ->whereIn('name', ['ALPHA - BNA', 'SMT - BNA', 'WEB - BNA', 'ALPHA - WEB', 'ALPHA - SMT', 'WEB - SMT'])
    ->get();

foreach ($duplicates as $rule) {
    foreach ($rule->matchingResults as $result) {
        $result->matchingDetails()->delete();
        $result->exceptions()->delete();
        $result->delete();
    }
    $rule->delete();
    echo "Deleted duplicate rule: " . $rule->name . " (ID: " . $rule->id . ")\n";
}

echo "Done. Remaining rules: " . App\Models\MatchingRule::count() . "\n";
