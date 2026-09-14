<?php

use App\Models\Import;
use App\Models\MatchingResult;
use App\Models\MatchingRule;
use App\Models\NormalizedTransaction;
use App\Models\Source;
use App\Models\Transaction;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$sources = Source::all()->pluck('name', 'code');
$rules = MatchingRule::all()->pluck('name', 'id');
$imports = Import::count();
$tx = Transaction::count();
$nt = NormalizedTransaction::count();
$results = MatchingResult::count();

echo 'Sources: '.$sources->implode(', ').PHP_EOL;
echo 'Rules: '.$rules->implode(', ').PHP_EOL;
echo 'Imports: '.$imports.PHP_EOL;
echo 'Transactions: '.$tx.PHP_EOL;
echo 'Normalized: '.$nt.PHP_EOL;
echo 'Results: '.$results.PHP_EOL;
