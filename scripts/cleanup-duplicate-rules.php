<?php

use App\Models\MatchingRule;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$duplicates = MatchingRule::query()
    ->whereIn('name', ['ALPHA - BNA', 'SMT - BNA', 'WEB - BNA', 'ALPHA - WEB', 'ALPHA - SMT', 'WEB - SMT'])
    ->get();

foreach ($duplicates as $rule) {
    foreach ($rule->matchingResults as $result) {
        $result->matchingDetails()->delete();
        $result->exceptions()->delete();
        $result->delete();
    }
    $rule->delete();
    echo 'Deleted duplicate rule: '.$rule->name.' (ID: '.$rule->id.")\n";
}

echo 'Done. Remaining rules: '.MatchingRule::count()."\n";
