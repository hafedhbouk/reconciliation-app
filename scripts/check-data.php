<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sources = App\Models\Source::all()->pluck('name', 'code');
$rules = App\Models\MatchingRule::all()->pluck('name', 'id');
$imports = App\Models\Import::count();
$tx = App\Models\Transaction::count();
$nt = App\Models\NormalizedTransaction::count();
$results = App\Models\MatchingResult::count();

echo 'Sources: ' . $sources->implode(', ') . PHP_EOL;
echo 'Rules: ' . $rules->implode(', ') . PHP_EOL;
echo 'Imports: ' . $imports . PHP_EOL;
echo 'Transactions: ' . $tx . PHP_EOL;
echo 'Normalized: ' . $nt . PHP_EOL;
echo 'Results: ' . $results . PHP_EOL;
